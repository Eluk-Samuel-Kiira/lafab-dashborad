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
    'qa_jobs.php' => [
        'icon' => 'fa-check-circle',
        'text' => 'QA Job Posting'
    ],
    'posters_stats.php' => [
        'icon' => 'fa-users',
        'text' => 'Posters Stats'
    ],
    'posters_timetable.php' => [
        'icon' => 'fa-calendar-alt',
        'text' => 'Posters Timetable'
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

// Check if we're on a country sync page to highlight the correct item
$is_country_sync_page = false;
$current_country = '';
foreach ($country_sync_items as $country => $sync_pages) {
    if ($current_page === $sync_pages['sync_companies'] || $current_page === $sync_pages['sync_jobs']) {
        $is_country_sync_page = true;
        $current_country = $country;
        break;
    }
}
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
            
            <!-- Country Sync Dropdown - Bootstrap 5 clickable dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="countrySyncDropdown" 
                   role="button" aria-expanded="false">
                    <i class="fas fa-globe-africa"></i> Country Sync
                    <?php if ($is_country_sync_page): ?>
                        <span class="badge bg-info ms-1"><?php echo $current_country; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu bg-dark" aria-labelledby="countrySyncDropdown">
                    <?php foreach ($country_sync_items as $country => $sync_pages): ?>
                        <li>
                            <h6 class="dropdown-header text-light border-bottom pb-2 mb-2">
                                <i class="fas fa-map-marker-alt me-2"></i><?php echo $country; ?>
                            </h6>
                            <div class="dropdown-sublinks ps-3">
                                <a class="dropdown-item <?php echo $current_page === $sync_pages['sync_companies'] ? 'active' : ''; ?>" 
                                   href="<?php echo $sync_pages['sync_companies']; ?>">
                                    <i class="fas fa-building me-2"></i>Sync Companies
                                </a>
                                <a class="dropdown-item <?php echo $current_page === $sync_pages['sync_jobs'] ? 'active' : ''; ?>" 
                                    href="<?php echo $sync_pages['sync_jobs']; ?>?country=<?php echo $sync_pages['code']; ?>">
                                    <i class="fas fa-exchange-alt me-2"></i>Sync Jobs
                                </a>
                            </div>
                            <?php if ($country !== 'Malawi'): ?>
                                <div class="dropdown-divider my-2"></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="mobile-sidebar-overlay d-md-none" id="mobileOverlay"></div>

<style>
/* Desktop Sidebar - Keep your existing desktop styles */
.sidebar {
    min-height: 100vh;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    z-index: 100;
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

/* Keep your existing dropdown styles */
.dropdown-menu {
    background-color: #343a40;
    border: 1px solid rgba(255,255,255,.1);
    min-width: 250px;
    padding: 0.5rem 0;
    margin-top: 0;
    border-radius: 0 0 6px 6px;
}

.dropdown-item {
    color: rgba(255,255,255,.75);
    padding: 0.5rem 1rem;
    transition: all 0.2s;
    border-radius: 4px;
    margin: 0 0.5rem;
    width: calc(100% - 1rem);
}

.dropdown-item:hover, 
.dropdown-item:focus, 
.dropdown-item.active {
    background-color: #495057;
    color: white;
}

.dropdown-item.active {
    background-color: #007bff;
    color: white;
}

.dropdown-header {
    color: #adb5bd !important;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    margin-top: 0.5rem;
}

.dropdown-sublinks {
    margin-bottom: 0.5rem;
}

.dropdown-divider {
    border-top: 1px solid rgba(255,255,255,.1);
    margin: 0.5rem 1rem;
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
.nav-link.active,
.nav-link.show {
    color: white;
    background-color: rgba(255,255,255,.1);
}

.nav-link.active {
    background-color: #007bff;
}

.dropdown-toggle::after {
    margin-left: auto;
    transition: transform 0.2s;
}

.dropdown-toggle[aria-expanded="true"]::after {
    transform: rotate(180deg);
}

/* Make sure dropdown stays open when clicking inside */
.dropdown-menu.show {
    display: block;
}

/* Fix for dropdown positioning */
.dropdown {
    position: relative;
}

/* Add scroll for long dropdowns */
.dropdown-menu {
    max-height: 70vh;
    overflow-y: auto;
}

/* Scrollbar styling */
.dropdown-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-menu::-webkit-scrollbar-track {
    background: rgba(255,255,255,.1);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.3);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,.5);
}

.badge {
    font-size: 0.7em;
    padding: 0.2em 0.5em;
}

/* MOBILE FIXES */
@media (max-width: 768px) {
    .sidebar {
        min-height: auto;
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
    
    /* Fix dropdown positioning on mobile */
    .dropdown-menu {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: 100% !important;
        transform: none !important;
        width: 100% !important;
        border: 1px solid rgba(255,255,255,.1) !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        max-height: 60vh;
    }
    
    /* Prevent dropdown from hiding when clicking inside */
    .dropdown-menu.show {
        position: absolute !important;
        display: block !important;
    }
    
    /* Make sure dropdown items are touch-friendly on mobile */
    .dropdown-item {
        padding: 12px 16px !important;
        font-size: 16px;
        margin: 0;
        border-radius: 0;
        width: 100%;
    }
    
    .dropdown-header {
        padding: 12px 16px;
    }
    
    .dropdown-divider {
        margin: 0.5rem 0;
    }
    
    /* Increase sidebar height to accommodate more items */
    .sidebar {
        overflow-y: auto;
    }
}

/* Desktop - hide mobile elements */
@media (min-width: 769px) {
    .mobile-sidebar-toggle,
    .mobile-sidebar-overlay {
        display: none !important;
    }
}

/* Dropdown positioning fix for desktop */
@media (min-width: 769px) {
    .dropdown-menu {
        position: absolute;
        transform: translate3d(0px, 40px, 0px) !important;
        top: 0;
        left: 0;
        will-change: transform;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const countryDropdown = document.getElementById('countrySyncDropdown');
    const countryDropdownMenu = countryDropdown.nextElementSibling;
    
    // Mobile sidebar toggle
    mobileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('show');
        mobileOverlay.classList.toggle('show');
    });
    
    // Close sidebar when clicking overlay
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        this.classList.remove('show');
        
        // Also close dropdown if open
        if (countryDropdown.getAttribute('aria-expanded') === 'true') {
            countryDropdown.setAttribute('aria-expanded', 'false');
            countryDropdownMenu.classList.remove('show');
        }
    });
    
    // Handle dropdown on mobile and desktop
    countryDropdown.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !isExpanded);
        countryDropdownMenu.classList.toggle('show');
        
        // On mobile, keep sidebar open when opening dropdown
        if (window.innerWidth <= 768 && !isExpanded) {
            e.stopImmediatePropagation();
        }
    });
    
    // Prevent dropdown from closing when clicking inside
    countryDropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // Close sidebar when clicking a dropdown item on mobile
        if (window.innerWidth <= 768 && e.target.closest('.dropdown-item')) {
            setTimeout(() => {
                sidebar.classList.remove('show');
                mobileOverlay.classList.remove('show');
            }, 100);
        }
    });
    
    // Close dropdown when clicking outside (desktop only)
    if (window.innerWidth > 768) {
        document.addEventListener('click', function(e) {
            if (!countryDropdown.contains(e.target) && !countryDropdownMenu.contains(e.target)) {
                countryDropdown.setAttribute('aria-expanded', 'false');
                countryDropdownMenu.classList.remove('show');
            }
        });
    }
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            countryDropdown.setAttribute('aria-expanded', 'false');
            countryDropdownMenu.classList.remove('show');
            
            // Also close sidebar on mobile if open
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                mobileOverlay.classList.remove('show');
            }
        }
    });
    
    // Handle active states for dropdown items
    const dropdownItems = countryDropdownMenu.querySelectorAll('.dropdown-item');
    dropdownItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            dropdownItems.forEach(i => i.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Update parent dropdown text to show selected country
            const countryHeader = this.closest('li').querySelector('.dropdown-header');
            if (countryHeader) {
                const countryName = countryHeader.textContent.trim();
                const countryBadge = document.createElement('span');
                countryBadge.className = 'badge bg-info ms-1';
                countryBadge.textContent = countryName;
                
                // Remove existing badge if any
                const existingBadge = countryDropdown.querySelector('.badge');
                if (existingBadge) {
                    existingBadge.remove();
                }
                
                // Add new badge
                countryDropdown.appendChild(countryBadge);
            }
        });
    });
    
    // Handle all nav link clicks on mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            // On mobile, close sidebar after clicking any nav link (except dropdown toggle)
            if (window.innerWidth <= 768 && !this.classList.contains('dropdown-toggle')) {
                setTimeout(() => {
                    sidebar.classList.remove('show');
                    mobileOverlay.classList.remove('show');
                }, 100);
            }
        });
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target) &&
            sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            mobileOverlay.classList.remove('show');
            
            // Also close dropdown if open
            if (countryDropdown.getAttribute('aria-expanded') === 'true') {
                countryDropdown.setAttribute('aria-expanded', 'false');
                countryDropdownMenu.classList.remove('show');
            }
        }
    });
    
    // Close sidebar on window resize if going to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            mobileOverlay.classList.remove('show');
            
            // Close dropdown on desktop resize
            if (countryDropdown.getAttribute('aria-expanded') === 'true') {
                countryDropdown.setAttribute('aria-expanded', 'false');
                countryDropdownMenu.classList.remove('show');
            }
        }
    });
});
</script>