// Global cart sync functionality
// This script should be included on every page to handle post-login cart sync

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        checkAndSyncCart();
    });
    
    async function checkAndSyncCart() {
        try {
            // Check if cart sync is needed
            const response = await fetch('cart.php?action=check_sync_needed');
            const result = await response.json();
            
            if (result.status === 'success' && result.sync_needed) {
                console.log('Cart sync needed after login');
                
                // Get localStorage cart
                const localCart = JSON.parse(localStorage.getItem('cart') || '[]');
                
                if (localCart.length > 0) {
                    console.log('Found localStorage cart items:', localCart.length);
                    await syncCartToDatabase(localCart);
                } else {
                    console.log('No localStorage cart items to sync');
                    // Clear the sync flag even if no items
                    await clearSyncFlag();
                }
            }
        } catch (error) {
            console.error('Error checking cart sync status:', error);
        }
    }
    
    async function syncCartToDatabase(localCart) {
        try {
            const response = await fetch('cart.php?action=sync_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ cart: localCart })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                console.log('Cart synced successfully:', result.message);
                
                // Clear localStorage after successful sync
                localStorage.removeItem('cart');
                console.log('LocalStorage cart cleared');
                
                // Show a subtle notification if possible
                showCartSyncNotification(true, result.message);
                
                // Update cart badge if the function exists
                if (typeof updateCartBadge === 'function') {
                    updateCartBadge();
                }
            } else {
                console.error('Cart sync failed:', result.message);
                showCartSyncNotification(false, result.message);
            }
        } catch (error) {
            console.error('Error syncing cart:', error);
            showCartSyncNotification(false, 'Network error during sync');
        }
    }
    
    async function clearSyncFlag() {
        try {
            // Send empty cart to clear the sync flag
            await fetch('cart.php?action=sync_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ cart: [] })
            });
        } catch (error) {
            console.error('Error clearing sync flag:', error);
        }
    }
    
    function showCartSyncNotification(success, message) {
        // Try to use existing toast function if available
        if (typeof showToast === 'function') {
            showToast(message, success ? 'success' : 'error');
            return;
        }
        
        // Fallback: create a simple notification
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${success ? '#d4edda' : '#f8d7da'};
            color: ${success ? '#155724' : '#721c24'};
            border: 1px solid ${success ? '#c3e6cb' : '#f5c6cb'};
            border-radius: 4px;
            font-size: 14px;
            z-index: 10000;
            max-width: 300px;
            word-wrap: break-word;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    // Make function available globally for manual testing
    window.manualCartSync = checkAndSyncCart;
    
})();