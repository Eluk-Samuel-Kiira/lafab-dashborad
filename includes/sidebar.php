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
            
            <!-- Country Sync Dropdown - Bootstrap 5 clickable dropdown -->
            <li class="nav-item">
                <a class="nav-link dropdown-toggle" href="#" id="countrySyncDropdown" 
                   role="button" data-bs-toggle="dropdown" aria-expanded="false">
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

<!-- Add Bootstrap and custom CSS for better dropdowns -->
<style>
.sidebar {
    min-height: 100vh;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    z-index: 100;
}

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

/* Mobile responsiveness */
@media (max-width: 768px) {
    .sidebar {
        min-height: auto;
        position: fixed;
        top: 0;
        left: -100%;
        width: 250px;
        transition: left 0.3s;
    }
    
    .sidebar.show {
        left: 0;
    }
    
    .dropdown-menu {
        position: static !important;
        transform: none !important;
        width: 100%;
        border: none;
        box-shadow: none;
    }
}
</style>

<!-- JavaScript to handle dropdown behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the country sync dropdown
    const countryDropdown = document.getElementById('countrySyncDropdown');
    const countryDropdownMenu = countryDropdown.nextElementSibling;
    
    // Prevent dropdown from closing when clicking inside
    countryDropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Toggle dropdown on click
    countryDropdown.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !isExpanded);
        countryDropdownMenu.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!countryDropdown.contains(e.target) && !countryDropdownMenu.contains(e.target)) {
            countryDropdown.setAttribute('aria-expanded', 'false');
            countryDropdownMenu.classList.remove('show');
        }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            countryDropdown.setAttribute('aria-expanded', 'false');
            countryDropdownMenu.classList.remove('show');
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
});
</script>