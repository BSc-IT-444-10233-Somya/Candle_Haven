// Cart functionality
document.addEventListener('DOMContentLoaded', function() {
    // Note: add-to-cart click handlers are handled by page-specific scripts
    // (e.g. `js/main.js` for product cards, `js/shop.js` for product page).
    
    // Update quantity in cart page
    const quantityButtons = document.querySelectorAll('.quantity-btn');
    
    quantityButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = this.closest('form');
            const input = form.querySelector('.quantity-input');
            let quantity = parseInt(input.value);
            
            if(this.classList.contains('plus')) {
                quantity++;
            } else if(this.classList.contains('minus') && quantity > 1) {
                quantity--;
            }
            
            input.value = quantity;
            
            // Submit form via AJAX
            updateCartItem(form);
        });
    });
    
    // Remove item
    const removeButtons = document.querySelectorAll('.remove-item');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if(!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            const form = this.closest('form');
            removeCartItem(form);
        });
    });
    
    // Cart quantity changes
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            let quantity = parseInt(this.value);
            
            if(quantity < 1) {
                quantity = 1;
                this.value = quantity;
            }
            
            updateCartItem(form);
        });
    });
    
    // Add event listener to state dropdown
    const stateDropdown = document.getElementById('state');
    const districtDropdown = document.getElementById('district');
    
    if (stateDropdown && districtDropdown) {
        const populateDistricts = function(state) {
            // Clear existing district options
            districtDropdown.innerHTML = '<option value="">Select District</option>';

            if (state === 'Bihar') {
                const districts = [
                    'Araria','Arwal','Aurangabad','Banka','Begusarai','Bhagalpur','Bhojpur','Buxar',
                    'Darbhanga','East Champaran','Gaya','Gopalganj','Jamui','Jehanabad','Kaimur','Katihar',
                    'Khagaria','Kishanganj','Lakhisarai','Madhepura','Madhubani','Munger','Muzaffarpur',
                    'Nalanda','Nawada','Patna','Purnia','Rohtas','Saharsa','Samastipur','Saran',
                    'Sheikhpura','Sheohar','Sitamarhi','Siwan','Supaul','Vaishali','West Champaran'
                ];

                districts.forEach(district => {
                    const option = document.createElement('option');
                    option.value = district;
                    option.textContent = district;
                    districtDropdown.appendChild(option);
                });
            }
        };

        stateDropdown.addEventListener('change', function () {
            populateDistricts(stateDropdown.value);
        });

        // Populate on load if a state is already selected
        if (stateDropdown.value) {
            populateDistricts(stateDropdown.value);
        }
    }
});

// AJAX function to add to cart
function addToCart(productId, productName, productPrice, button, productPriceInr) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    formData.append('ajax', 'true');
    
    fetch('add-to-cart-ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update cart count (support both keys from different endpoints)
            const cartCount = data.cartCount !== undefined ? data.cartCount : (data.cart_count !== undefined ? data.cart_count : 0);
            updateCartCount(cartCount);

            // Show success message (include INR formatted price if provided)
            const priceLabel = productPriceInr ? ` (${productPriceInr})` : '';
            showNotification(`${productName}${priceLabel} added to cart!`, 'success');

            // Animate cart icon if button is provided
            if(button) {
                animateCartIcon(button);
            }
        } else {
            showNotification(data.message || 'Error adding to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// AJAX function to update cart item
function updateCartItem(form) {
    const formData = new FormData(form);
    formData.append('ajax', 'true');
    
    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Reload the page to show updated cart
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating cart. Please try again.', 'error');
    });
}

// AJAX function to remove cart item
function removeCartItem(form) {
    const formData = new FormData(form);
    formData.append('ajax', 'true');
    
    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Reload the page to show updated cart
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error removing item. Please try again.', 'error');
    });
}

// Update cart count display
function updateCartCount(count) {
    let cartCountElements = document.querySelectorAll('.cart-count');

    // If no cart-count exists in the header, create one
    if (cartCountElements.length === 0) {
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            const newCartCount = document.createElement('span');
            newCartCount.className = 'cart-count';
            newCartCount.style.display = 'none';
            cartIcon.appendChild(newCartCount);
            // refresh NodeList
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
}

// Animate cart icon
function animateCartIcon(button) {
    const cartIcon = document.querySelector('.cart-icon');
    if(!cartIcon) return;
    
    const buttonRect = button.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();
    
    // Create flying element
    const flyingItem = document.createElement('div');
    flyingItem.className = 'flying-item';
    flyingItem.innerHTML = '<i class="fas fa-shopping-cart"></i>';
    
    flyingItem.style.position = 'fixed';
    flyingItem.style.left = buttonRect.left + 'px';
    flyingItem.style.top = buttonRect.top + 'px';
    flyingItem.style.zIndex = '9999';
    flyingItem.style.fontSize = '1.5rem';
    flyingItem.style.color = 'var(--primary-color)';
    flyingItem.style.transition = 'all 0.8s cubic-bezier(0.215, 0.610, 0.355, 1)';
    
    document.body.appendChild(flyingItem);
    
    // Trigger animation
    setTimeout(() => {
        flyingItem.style.left = cartRect.left + 'px';
        flyingItem.style.top = cartRect.top + 'px';
        flyingItem.style.transform = 'scale(0.5)';
        flyingItem.style.opacity = '0.5';
    }, 10);
    
    // Remove flying element after animation
    setTimeout(() => {
        flyingItem.remove();
        
        // Bounce cart icon
        cartIcon.style.transform = 'scale(1.2)';
        setTimeout(() => {
            cartIcon.style.transform = 'scale(1)';
        }, 300);
    }, 800);
}

// Checkout form validation
function validateCheckoutForm() {
    const shippingAddress = document.getElementById('shipping_address');
    const city = document.getElementById('city');
    const state = document.getElementById('state');
    const zip = document.getElementById('zip');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    
    let errors = [];
    
    if(!shippingAddress || !shippingAddress.value.trim()) {
        errors.push('Shipping address is required.');
    }
    
    if(!city || !city.value.trim()) {
        errors.push('City is required.');
    }
    
    if(!state || !state.value) {
        errors.push('State is required.');
    }
    
    if(!zip || !zip.value.trim() || !/^\d{5}$/.test(zip.value)) {
        errors.push('Valid ZIP code is required.');
    }
    
    if(!paymentMethod) {
        errors.push('Payment method is required.');
    }
    
    if(paymentMethod && paymentMethod.value === 'credit_card') {
        const cardNumber = document.getElementById('card_number');
        const expiryDate = document.getElementById('expiry_date');
        const cvv = document.getElementById('cvv');
        const cardName = document.getElementById('card_name');
        
        if(!cardNumber || !cardNumber.value.trim()) {
            errors.push('Card number is required.');
        }
        
        if(!expiryDate || !expiryDate.value.trim() || !/^\d{2}\/\d{2}$/.test(expiryDate.value)) {
            errors.push('Valid expiry date (MM/YY) is required.');
        }
        
        if(!cvv || !cvv.value.trim() || !/^\d{3,4}$/.test(cvv.value)) {
            errors.push('Valid CVV is required.');
        }
        
        if(!cardName || !cardName.value.trim()) {
            errors.push('Name on card is required.');
        }
    }
    
    if(errors.length > 0) {
        showNotification(errors.join('<br>'), 'error');
        return false;
    }
    
    return true;
}

// Format card number
function formatCardNumber(input) {
    if(!input) return;
    
    let value = input.value.replace(/\D/g, '');
    let formatted = '';
    
    for(let i = 0; i < value.length && i < 16; i++) {
        if(i > 0 && i % 4 === 0) {
            formatted += ' ';
        }
        formatted += value[i];
    }
    
    input.value = formatted;
}

// Format expiry date
function formatExpiryDate(input) {
    if(!input) return;
    
    let value = input.value.replace(/\D/g, '');
    
    if(value.length >= 2) {
        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
    } else {
        input.value = value;
    }
}

// Initialize checkout form formatting
document.addEventListener('DOMContentLoaded', function() {
    const cardNumberInput = document.getElementById('card_number');
    const expiryDateInput = document.getElementById('expiry_date');
    
    if(cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            formatCardNumber(this);
        });
        
        cardNumberInput.addEventListener('keypress', function(e) {
            if(!/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    if(expiryDateInput) {
        expiryDateInput.addEventListener('input', function() {
            formatExpiryDate(this);
        });
        
        expiryDateInput.addEventListener('keypress', function(e) {
            if(!/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // CVV input validation
    const cvvInput = document.getElementById('cvv');
    if(cvvInput) {
        cvvInput.addEventListener('keypress', function(e) {
            if(!/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // ZIP code validation
    const zipInput = document.getElementById('zip');
    if(zipInput) {
        zipInput.addEventListener('keypress', function(e) {
            if(!/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Toggle payment details based on selected method
    const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit_card_details');
    
    if(paymentOptions.length > 0 && creditCardDetails) {
        function togglePaymentDetails() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if(!selectedMethod) return;
            
            if(selectedMethod.value === 'credit_card') {
                creditCardDetails.style.display = 'block';
                // Make credit card fields required
                if(cardNumberInput) cardNumberInput.required = true;
                if(expiryDateInput) expiryDateInput.required = true;
                if(cvvInput) cvvInput.required = true;
                if(document.getElementById('card_name')) document.getElementById('card_name').required = true;
            } else {
                creditCardDetails.style.display = 'none';
                // Remove required attribute for non-credit card payments
                if(cardNumberInput) cardNumberInput.required = false;
                if(expiryDateInput) expiryDateInput.required = false;
                if(cvvInput) cvvInput.required = false;
                if(document.getElementById('card_name')) document.getElementById('card_name').required = false;
            }
        }
        
        // Initial state
        togglePaymentDetails();
        
        // Add event listeners
        paymentOptions.forEach(option => {
            option.addEventListener('change', togglePaymentDetails);
        });
    }
});

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

// Add CSS for notifications if not already added
if(!document.querySelector('style[data-notification-styles]')) {
    const notificationStyles = document.createElement('style');
    notificationStyles.setAttribute('data-notification-styles', 'true');
    notificationStyles.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            max-width: 400px;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 9999;
            border-left: 4px solid #4CAF50;
        }
        
        .notification.error {
            border-left-color: #f44336;
        }
        
        .notification.success {
            border-left-color: #4CAF50;
        }
        
        .notification.info {
            border-left-color: #2196F3;
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
            padding: 0;
            line-height: 1;
        }
        
        .flying-item {
            position: fixed;
            z-index: 9999;
            pointer-events: none;
        }
    `;
    document.head.appendChild(notificationStyles);
}