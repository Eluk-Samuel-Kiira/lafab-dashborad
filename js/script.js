// Enhanced table responsive functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-format dates if needed
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });

    // Enhanced table responsive scrolling with visual indicators
    initTableScrollIndicators();
    
    // Add touch scrolling for mobile
    initTouchScrolling();
    
    // Auto-focus management for better UX
    initAutoFocus();
});

// Initialize scroll indicators for responsive tables
function initTableScrollIndicators() {
    const tableContainers = document.querySelectorAll('.table-responsive');
    
    tableContainers.forEach(container => {
        const table = container.querySelector('table');
        
        if (table && table.scrollWidth > container.clientWidth) {
            // Add scroll event listener
            container.addEventListener('scroll', function() {
                updateScrollIndicators(this);
            });
            
            // Check on resize
            window.addEventListener('resize', function() {
                updateScrollIndicators(container);
            });
            
            // Initial check
            updateScrollIndicators(container);
        }
    });
}

// Update scroll indicators based on scroll position
function updateScrollIndicators(container) {
    const scrollLeft = container.scrollLeft;
    const scrollWidth = container.scrollWidth;
    const clientWidth = container.clientWidth;
    
    // Remove existing classes
    container.classList.remove('scroll-left', 'scroll-right');
    
    // Add appropriate classes
    if (scrollLeft > 0) {
        container.classList.add('scroll-left');
    }
    
    if (scrollLeft < (scrollWidth - clientWidth - 1)) {
        container.classList.add('scroll-right');
    }
}

// Initialize touch scrolling for mobile devices
function initTouchScrolling() {
    let isDown = false;
    let startX;
    let scrollLeft;
    let scrollContainer;

    document.querySelectorAll('.table-responsive').forEach(container => {
        container.addEventListener('mousedown', (e) => {
            isDown = true;
            scrollContainer = container;
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
            container.style.cursor = 'grabbing';
        });

        container.addEventListener('mouseleave', () => {
            isDown = false;
            if (scrollContainer) scrollContainer.style.cursor = 'grab';
        });

        container.addEventListener('mouseup', () => {
            isDown = false;
            if (scrollContainer) scrollContainer.style.cursor = 'grab';
        });

        container.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 2; // Scroll multiplier
            container.scrollLeft = scrollLeft - walk;
        });

        // Touch events for mobile
        container.addEventListener('touchstart', (e) => {
            isDown = true;
            scrollContainer = container;
            startX = e.touches[0].pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        });

        container.addEventListener('touchend', () => {
            isDown = false;
        });

        container.addEventListener('touchmove', (e) => {
            if (!isDown) return;
            const x = e.touches[0].pageX - container.offsetLeft;
            const walk = (x - startX) * 2;
            container.scrollLeft = scrollLeft - walk;
        });
    });
}

// Auto-focus management for better UX
function initAutoFocus() {
    // Auto-focus on first form input in modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const input = this.querySelector('input[type="text"], input[type="email"], input[type="password"], textarea');
            if (input) {
                input.focus();
            }
        });
    });
    
    // Smooth scroll to focused element on mobile
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            if (window.innerWidth < 768) {
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        });
    });
}

// Utility function to check if element is in viewport
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Refresh table scroll indicators (call this after dynamic content loads)
function refreshTableScroll() {
    initTableScrollIndicators();
}