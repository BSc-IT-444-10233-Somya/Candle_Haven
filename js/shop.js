// Shop Page Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Price Range Slider
    const minPriceSlider = document.getElementById('minPriceSlider');
    const maxPriceSlider = document.getElementById('maxPriceSlider');
    const minPriceInput = document.getElementById('minPrice');
    const maxPriceInput = document.getElementById('maxPrice');
    const applyPriceFilter = document.getElementById('applyPriceFilter');
    const sliderTrack = document.querySelector('.slider-track');

    if (minPriceSlider && maxPriceSlider) {
        function updateSliderTrack() {
            const min = parseInt(minPriceSlider.value);
            const max = parseInt(maxPriceSlider.value);
            const minPercent = (min / maxPriceSlider.max) * 100;
            const maxPercent = (max / maxPriceSlider.max) * 100;
            
            sliderTrack.style.left = minPercent + '%';
            sliderTrack.style.width = (maxPercent - minPercent) + '%';
        }

        function updateInputs() {
            minPriceInput.value = minPriceSlider.value;
            maxPriceInput.value = maxPriceSlider.value;
            updateSliderTrack();
        }

        minPriceSlider.addEventListener('input', function() {
            const minValue = parseInt(this.value);
            const maxValue = parseInt(maxPriceSlider.value);
            
            if (minValue > maxValue) {
                this.value = maxValue;
            }
            updateInputs();
        });

        maxPriceSlider.addEventListener('input', function() {
            const minValue = parseInt(minPriceSlider.value);
            const maxValue = parseInt(this.value);
            
            if (maxValue < minValue) {
                this.value = minValue;
            }
            updateInputs();
        });

        minPriceInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            const max = parseInt(maxPriceSlider.max);
            
            if (value < 0) value = 0;
            if (value > max) value = max;
            
            minPriceSlider.value = value;
            updateSliderTrack();
        });

        maxPriceInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            const max = parseInt(maxPriceSlider.max);
            
            if (value < 0) value = 0;
            if (value > max) value = max;
            
            maxPriceSlider.value = value;
            updateSliderTrack();
        });

        applyPriceFilter.addEventListener('click', function() {
            const minPrice = minPriceInput.value;
            const maxPrice = maxPriceInput.value;
            
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('min_price', minPrice);
            urlParams.set('max_price', maxPrice);
            urlParams.set('page', '1'); // Reset to first page
            
            window.location.href = 'shop.php?' + urlParams.toString();
        });

        updateSliderTrack();
    }

    // Sort Select Change
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', this.value);
            urlParams.set('page', '1');
            
            window.location.href = 'shop.php?' + urlParams.toString();
        });
    }

    // View Toggle
    const viewButtons = document.querySelectorAll('.view-btn');
    const productsView = document.getElementById('productsView');
    const productsGrid = document.getElementById('productsGrid');

    if (viewButtons.length > 0 && productsGrid) {
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const view = this.getAttribute('data-view');
                
                // Update active button
                viewButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Change view
                if (view === 'list') {
                    productsGrid.classList.add('list-view');
                } else {
                    productsGrid.classList.remove('list-view');
                }
            });
        });
    }

    // In Stock Filter
    const inStockCheckbox = document.getElementById('inStockOnly');
    if (inStockCheckbox) {
        inStockCheckbox.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (this.checked) {
                urlParams.set('instock', '1');
            } else {
                urlParams.delete('instock');
            }
            urlParams.set('page', '1');
            
            window.location.href = 'shop.php?' + urlParams.toString();
        });
    }

    // Add to Cart Buttons
    const addToCartButtons = document.querySelectorAll('.btn-add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            const productPrice = this.getAttribute('data-price');
            const productPriceInr = this.getAttribute('data-price-inr') || null;
            
            // Send AJAX request
            fetch('add-to-cart-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    updateCartCount(data.cartCount);
                    
                    // Show notification (prefer INR formatted price when available)
                    const priceLabel = productPriceInr ? ` (${productPriceInr})` : '';
                    showNotification(`${productName}${priceLabel} added to cart!`, 'success');
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Network error. Please try again.', 'error');
            });
        });
    });

    // Wishlist Buttons
    const wishlistButtons = document.querySelectorAll('.btn-wishlist');
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-id');
            
            if (!isLoggedIn()) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            }
            
            fetch('toggle-wishlist.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(async response => {
                const text = await response.text();
                let data;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (err) {
                    console.error('Invalid JSON from toggle-wishlist.php:', text);
                    showNotification('Server error. Check console for details.', 'error');
                    return;
                }

                console.log('toggle-wishlist response:', response.status, data);

                if (!response.ok) {
                    showNotification(data.message || 'Error updating wishlist', 'error');
                    return;
                }

                if (data.success) {
                    const icon = this.querySelector('i');
                    if (data.added) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        showNotification(data.message || 'Added to wishlist!', 'success');
                    } else {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        showNotification(data.message || 'Removed from wishlist', 'info');
                    }
                } else {
                    showNotification(data.message || 'Error updating wishlist', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error (toggle-wishlist):', error);
                showNotification('Network error. Please try again.', 'error');
            });
        });
    });

    // Helper functions
    function isLoggedIn() {
        return document.body.classList.contains('logged-in');
    }

    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            
            // Show/hide cart count badge
            if (count > 0) {
                element.style.display = 'flex';
            } else {
                element.style.display = 'none';
            }
        });
    }

    function showNotification(message, type) {
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
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 5000);
        
        // Close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', function() {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        });
    }
});

// List View Styles (to be added dynamically)
const listViewStyles = `
.products-grid.list-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.products-grid.list-view .product-card {
    display: flex;
    gap: 30px;
    padding: 20px;
}

.products-grid.list-view .product-image {
    width: 200px;
    height: 200px;
    flex-shrink: 0;
}

.products-grid.list-view .product-info {
    flex: 1;
    padding: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.products-grid.list-view .product-specs {
    display: none;
}

.products-grid.list-view .product-description {
    display: block;
    margin-bottom: 15px;
    color: var(--text-light);
    font-size: 0.9rem;
    line-height: 1.5;
}

.products-grid.list-view .product-description {
    display: none;
}

.products-grid.list-view .product-actions {
    justify-content: flex-start;
}
`;

// Add list view styles to head
const style = document.createElement('style');
style.textContent = listViewStyles;
document.head.appendChild(style);