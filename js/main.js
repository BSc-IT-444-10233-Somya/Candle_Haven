// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mobileNav = document.querySelector('.mobile-nav');
    
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileNav.classList.toggle('active');
        });
    }
    
    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            const productPrice = this.getAttribute('data-price');
            const productPriceInr = this.getAttribute('data-price-inr') || null;
            
            // Send AJAX request to add to cart using unified AJAX endpoint
            const payload = new URLSearchParams();
            payload.append('product_id', productId);
            payload.append('quantity', 1);
            payload.append('ajax', 'true');

            fetch('add-to-cart-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: payload
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const cartCount = data.cartCount !== undefined ? data.cartCount : (data.cart_count !== undefined ? data.cart_count : null);
                    if (cartCount !== null) updateCartCount(cartCount);

                    const priceLabel = productPriceInr ? ` (${productPriceInr})` : '';
                    showNotification(`${productName}${priceLabel} added to cart!`, 'success');
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(err => {
                console.error('Add to cart error', err);
                showNotification('Network error. Please try again.', 'error');
            });
        });
    });
    
    // Newsletter form submission
    const newsletterForm = document.querySelector('.newsletter-form');
    if(newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            
            // Simple validation
            if(email && validateEmail(email)) {
                showNotification('Thank you for subscribing!', 'success');
                this.reset();
            } else {
                showNotification('Please enter a valid email address.', 'error');
            }
        });
    }
});

// Email validation
function validateEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Notification function
function showNotification(message, type) {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if(existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if(notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
    
    // Close button functionality
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', function() {
        notification.classList.remove('show');
        setTimeout(() => {
            if(notification.parentNode) {
                notification.remove();
            }
        }, 300);
    });
}

// Add CSS for notifications
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 9999;
        border-left: 4px solid #4CAF50;
    }
    
    .notification.error {
        border-left-color: #f44336;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #777;
        margin-left: 15px;
    }
`;
document.head.appendChild(notificationStyles);

// Global cart count updater used by page scripts
function updateCartCount(count) {
    try {
        console.debug('updateCartCount called with', count);
        let cartCountElements = document.querySelectorAll('.cart-count');

        if (cartCountElements.length === 0) {
            const cartIcon = document.querySelector('.cart-icon');
            if (cartIcon) {
                const newCartCount = document.createElement('span');
                newCartCount.className = 'cart-count';
                newCartCount.style.display = 'none';
                cartIcon.appendChild(newCartCount);
                cartCountElements = document.querySelectorAll('.cart-count');
            }
        }

        cartCountElements.forEach(element => {
            if (count > 0) {
                element.textContent = count;
                element.style.display = 'flex';
            } else {
                element.textContent = '0';
                element.style.display = 'none';
            }
        });
    } catch (e) {
        console.error('updateCartCount error', e);
    }
}