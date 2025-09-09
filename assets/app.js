import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
console.log('App loaded');
// Initialize Bootstrap components
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Show a welcome toast
    if (window.SenBillets) {
        window.SenBillets.showToast('Bienvenue sur Sen-Billets!', 'info');
    }
    
    // Initialize dropdowns
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    // Handle navigation links
    setupNavigation();
});

// Setup client-side navigation
function setupNavigation() {
    // Get all navigation links
    const navLinks = document.querySelectorAll('a[href^="/"]');
    
    // Add click event listener to each link
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Skip if it's an external link or has target="_blank"
            if (this.getAttribute('target') === '_blank' || href.startsWith('http')) {
                return;
            }
            
            // Skip API routes
            if (href.startsWith('/api/')) {
                return;
            }
            
            // Handle navigation
            e.preventDefault();
            navigateTo(href);
        });
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', handleLocationChange);
    
    // Initial page load
    handleLocationChange();
}

// Navigate to a specific URL
function navigateTo(url) {
    history.pushState(null, null, url);
    handleLocationChange();
}

// Handle location changes
function handleLocationChange() {
    const path = window.location.pathname;
    
    // Update active state in navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === path) {
            link.classList.add('active');
        }
    });
    
    // Load the appropriate content based on the path
    loadContent(path);
}

// Load content based on path
function loadContent(path) {
    console.log('Navigation to:', path);
    
    // For now, we'll just reload the page to get the server to handle the routing
    window.location.href = path;
}

// Global utilities
window.SenBillets = {
    // Utility functions for the app
    formatPrice: function(price) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0
        }).format(price);
    },
    
    // Show toast notifications
    showToast: function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || this.createToastContainer();
        const toast = this.createToast(message, type);
        toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    },
    
    createToastContainer: function() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
        return container;
    },
    
    createToast: function(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        const iconMap = {
            success: 'bi-check-circle-fill text-success',
            error: 'bi-x-circle-fill text-danger',
            warning: 'bi-exclamation-triangle-fill text-warning',
            info: 'bi-info-circle-fill text-info'
        };
        
        toast.innerHTML = `
            <div class="toast-header">
                <i class="bi ${iconMap[type] || iconMap.info} me-2"></i>
                <strong class="me-auto">Sen-Billets</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        return toast;
    }
};

console.log('Sen-Billets app loaded successfully!');