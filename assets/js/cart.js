/**
 * Shopping Cart JavaScript Functions
 * Handles quantity updates and cart interactions
 */

// Increment quantity function
function incrementQuantity(button) {
    const input = button.previousElementSibling;
    const max = parseInt(input.max);
    const current = parseInt(input.value);
    
    if (current < max) {
        input.value = current + 1;
        input.form.submit();
    }
}

// Decrement quantity function
function decrementQuantity(button) {
    const input = button.nextElementSibling;
    const current = parseInt(input.value);
    
    if (current > 1) {
        input.value = current - 1;
        input.form.submit();
    }
}

// Auto-submit quantity changes after a delay
let quantityTimeout;

// Initialize quantity input handlers when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to quantity inputs
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(quantityTimeout);
            const inputValue = parseInt(this.value);
            const maxValue = parseInt(this.max);
            
            // Validate input
            if (isNaN(inputValue) || inputValue < 0) {
                this.value = 1;
                return;
            }
            
            if (inputValue > maxValue) {
                this.value = maxValue;
                showNotification('Maximum stock quantity is ' + maxValue, 'warning');
                return;
            }
            
            // Auto-submit after 1 second of no changes
            quantityTimeout = setTimeout(() => {
                if (inputValue >= 0 && inputValue <= maxValue) {
                    this.form.submit();
                }
            }, 1000);
        });

        // Prevent negative values and non-numeric input
        input.addEventListener('keydown', function(e) {
            // Allow: backspace, delete, tab, escape, enter
            if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // Handle paste events
        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                const value = parseInt(this.value);
                const max = parseInt(this.max);
                
                if (isNaN(value) || value < 0) {
                    this.value = 1;
                } else if (value > max) {
                    this.value = max;
                    showNotification('Maximum stock quantity is ' + max, 'warning');
                }
            }, 10);
        });
    });

    // Add smooth transitions to cart items
    const cartItems = document.querySelectorAll('.cart-item');
    cartItems.forEach(item => {
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    });

    // Add loading states to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="loading-spinner"></span> Processing...';
            }
        });
    });
});

// Notification function (if not already defined in main JS)
function showNotification(message, type = 'info') {
    // Check if notification system exists
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
        return;
    }
    
    // Simple fallback notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#007bff'};
        color: white;
        border-radius: 4px;
        z-index: 1000;
        opacity: 0;
        transform: translateX(100px);
        transition: all 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Remove notification after 4 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

// Handle quantity button states
function updateQuantityButtonStates() {
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    
    quantityInputs.forEach(input => {
        const decrementBtn = input.previousElementSibling;
        const incrementBtn = input.nextElementSibling;
        const currentValue = parseInt(input.value);
        const maxValue = parseInt(input.max);
        
        // Disable decrement button if quantity is 1 or less
        if (decrementBtn) {
            decrementBtn.disabled = currentValue <= 1;
            decrementBtn.style.opacity = currentValue <= 1 ? '0.5' : '1';
        }
        
        // Disable increment button if quantity equals max stock
        if (incrementBtn) {
            incrementBtn.disabled = currentValue >= maxValue;
            incrementBtn.style.opacity = currentValue >= maxValue ? '0.5' : '1';
        }
    });
}

// Update button states on page load and after quantity changes
document.addEventListener('DOMContentLoaded', updateQuantityButtonStates);

// Update button states when quantity changes
document.addEventListener('input', function(e) {
    if (e.target.name === 'quantity') {
        updateQuantityButtonStates();
    }
});

// Handle cart item animations
function animateCartItemRemoval(element) {
    element.style.transition = 'all 0.3s ease';
    element.style.opacity = '0';
    element.style.transform = 'translateX(-100px)';
    
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 300);
}

// Enhanced form validation
function validateCartForm(form) {
    const quantityInput = form.querySelector('input[name="quantity"]');
    if (quantityInput) {
        const value = parseInt(quantityInput.value);
        const max = parseInt(quantityInput.max);
        
        if (isNaN(value) || value < 0) {
            showNotification('Please enter a valid quantity', 'error');
            return false;
        }
        
        if (value > max) {
            showNotification(`Maximum stock quantity is ${max}`, 'error');
            return false;
        }
    }
    
    return true;
}

// Add form validation to all cart forms
document.addEventListener('DOMContentLoaded', function() {
    const cartForms = document.querySelectorAll('.quantity-form, .remove-form, .clear-cart-form');
    
    cartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateCartForm(this)) {
                e.preventDefault();
                return false;
            }
        });
    });
});

// Handle keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC to close any modals or cancel operations
    if (e.key === 'Escape') {
        const loadingButtons = document.querySelectorAll('button[disabled]');
        loadingButtons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.innerHTML.replace('<span class="loading-spinner"></span> Processing...', btn.dataset.originalText || 'Submit');
        });
    }
});

// Save original button text for loading states
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(btn => {
        btn.dataset.originalText = btn.innerHTML;
    });
});

// Handle offline/online states
window.addEventListener('online', function() {
    showNotification('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showNotification('You are offline. Changes may not be saved.', 'warning');
});

// Smooth scroll to top of cart after updates
function scrollToCartTop() {
    const cartContainer = document.querySelector('.cart-items-container');
    if (cartContainer) {
        cartContainer.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
}

// Auto-save cart state to session storage for recovery
function saveCartState() {
    const cartItems = [];
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    
    quantityInputs.forEach(input => {
        const cartId = input.form.querySelector('input[name="cart_id"]')?.value;
        if (cartId) {
            cartItems.push({
                cart_id: cartId,
                quantity: input.value
            });
        }
    });
    
    sessionStorage.setItem('cart_backup', JSON.stringify({
        timestamp: Date.now(),
        items: cartItems
    }));
}

// Save cart state when quantities change
document.addEventListener('input', function(e) {
    if (e.target.name === 'quantity') {
        saveCartState();
    }
});

// Initialize cart functionality
function initializeCart() {
    updateQuantityButtonStates();
    
    // Add visual feedback for interactive elements
    const interactiveElements = document.querySelectorAll('.cart-item, .btn, .quantity-input');
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.transition = 'transform 0.2s ease';
        });
    });
    
    // Add loading animation styles
    const style = document.createElement('style');
    style.textContent = `
        .loading-spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .notification {
            font-family: inherit;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .cart-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
        }
    `;
    document.head.appendChild(style);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCart);
} else {
    initializeCart();
}