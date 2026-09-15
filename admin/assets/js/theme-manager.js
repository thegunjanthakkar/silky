/**
 * Dark Mode State Manager
 * Saves and restores dark mode preference using localStorage
 */

// Apply saved theme immediately (before DOM loads)
(function() {
    const savedTheme = localStorage.getItem('silky_admin_theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    }
})();

// Initialize theme manager after DOM loads
document.addEventListener('DOMContentLoaded', function() {
    initThemeManager();
});

function initThemeManager() {
    const themeToggle = document.getElementById('light-dark-mode');
    
    if (themeToggle) {
        // Remove any existing event listeners by replacing the element
        const newThemeToggle = themeToggle.cloneNode(true);
        themeToggle.parentNode.replaceChild(newThemeToggle, themeToggle);
        
        // Add new event listener with localStorage save
        newThemeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleTheme();
        });
    }
    
    // Apply saved theme on page load
    applySavedTheme();
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    // Set the new theme
    document.documentElement.setAttribute('data-bs-theme', newTheme);
    document.documentElement.setAttribute('data-startbar', newTheme);
    
    // Save to localStorage with prefixed key
    localStorage.setItem('silky_admin_theme', newTheme);
    
    // Optional: Show notification
    console.log('Theme changed to:', newTheme);
    
    // Optional: You can add a toast notification here
    // showThemeChangeNotification(newTheme);
}

function applySavedTheme() {
    const savedTheme = localStorage.getItem('silky_admin_theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        document.documentElement.setAttribute('data-startbar', savedTheme);
        console.log('Applied saved theme:', savedTheme);
    }
}

// Function to get current theme
function getCurrentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
}

// Function to set specific theme
function setTheme(theme) {
    if (theme === 'light' || theme === 'dark') {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.setAttribute('data-startbar', theme);
        localStorage.setItem('silky_admin_theme', theme);
        console.log('Theme set to:', theme);
    }
}

// Optional: Function to show theme change notification
function showThemeChangeNotification(theme) {
    // You can implement a toast notification here if needed
    const message = theme === 'dark' ? 'Dark mode enabled' : 'Light mode enabled';
    
    // Example: Simple alert (you can replace with better notification system)
    // alert(message);
    
    // Or you can create a temporary notification element
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${theme === 'dark' ? '#333' : '#fff'};
        color: ${theme === 'dark' ? '#fff' : '#333'};
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 9999;
        transition: opacity 0.3s;
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 2 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 2000);
}

// Export functions for global use
window.silkyThemeManager = {
    toggle: toggleTheme,
    set: setTheme,
    get: getCurrentTheme,
    apply: applySavedTheme
};