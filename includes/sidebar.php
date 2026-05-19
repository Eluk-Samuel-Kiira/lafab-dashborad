<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define navigation items
$nav_items = [
    'dashboard.php' => [
        'icon' => 'fa-tachometer-alt',
        'text' => 'Dashboard'
    ],
    'qa_jobs.php' => [
        'icon' => 'fa-check-circle',
        'text' => 'QA Job Posting'
    ],
    // 'posters_timetable.php' => [
    //     'icon' => 'fa-calendar-alt',
    //     'text' => 'Posters Timetable'
    // ],
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

<!-- Mobile Toggle Button -->
<button class="mobile-sidebar-toggle d-md-none" id="mobileSidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="col-md-3 col-lg-2 bg-dark sidebar" id="sidebar">
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
        </ul>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-sidebar-overlay d-md-none" id="mobileOverlay"></div>

<style>
/* Desktop Sidebar */
.sidebar {
    min-height: 100vh;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    z-index: 100;
}

.nav-link {
    color: rgba(255,255,255,.75);
    padding: 0.75rem 1rem;
    border-radius: 4px;
    margin: 0.1rem 0.5rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}

.nav-link i {
    width: 20px;
    margin-right: 10px;
    text-align: center;
}

.nav-link:hover, 
.nav-link.active {
    color: white;
    background-color: rgba(255,255,255,.1);
}

.nav-link.active {
    background-color: #007bff;
}

/* Mobile Toggle Button */
.mobile-sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 9999;
    background: #343a40;
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

/* Mobile Overlay */
.mobile-sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 99;
    display: none;
}

/* Mobile Styles */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        width: 250px;
        transition: left 0.3s;
        z-index: 100;
        height: 100vh;
        overflow-y: auto;
    }
    
    .sidebar.show {
        left: 0;
    }
    
    .mobile-sidebar-overlay.show {
        display: block;
    }
}

/* Desktop - hide mobile elements */
@media (min-width: 769px) {
    .mobile-sidebar-toggle,
    .mobile-sidebar-overlay {
        display: none !important;
    }
}
</style>

<script>
// Simple mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!toggleBtn) return;
    
    // Open sidebar
    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.add('show');
        overlay.classList.add('show');
    });
    
    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
    
    // Close sidebar when clicking a nav link on mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    });
});
</script>