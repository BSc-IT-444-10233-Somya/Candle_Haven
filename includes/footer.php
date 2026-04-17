    </main>
    
    <!-- Newsletter Section -->
    <section class="newsletter">
        <div class="container">
            <h2>Stay in the Glow</h2>
            <p>Subscribe to our newsletter for exclusive offers, new arrivals, and candle care tips.</p>
            <form class="newsletter-form" id="newsletterForm" novalidate>
                <input type="text" id="newsletterName" name="newsletter_name" placeholder="Your name (optional)">
                <input type="email" id="newsletterEmail" name="newsletter_email" placeholder="Enter your email address">
                <button type="button" id="newsletterSubmit" class="btn">Subscribe</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p>Handcrafted candles made with natural ingredients to illuminate and fragrance your space.</p>
                    <div class="social-icons">
                        <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="https://pinterest.com" target="_blank"><i class="fab fa-pinterest-p"></i></a>
                        <a href="https://twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>index.php">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>shop.php">Shop</a></li>
                        <li><a href="<?php echo SITE_URL; ?>about.php">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>shipping.php">Shipping Policy</a></li>
                        <li><a href="<?php echo SITE_URL; ?>returns.php">Returns & Exchanges</a></li>
                        <li><a href="<?php echo SITE_URL; ?>faq.php">FAQs</a></li>
                        <li><a href="<?php echo SITE_URL; ?>privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> 123 Candle Street, Patna</li>
                        <li><i class="fas fa-phone"></i>7488042794</li>
                        <li><i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?></li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9am-6pm</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>js/main.js"></script>
    <script src="<?php echo SITE_URL; ?>js/cart.js"></script>
    <script>
    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileNav = document.getElementById('mobileNav');
        
        if (mobileMenuBtn && mobileNav) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileNav.classList.toggle('active');
                const icon = this.querySelector('i');
                if (mobileNav.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
            
            // Close mobile menu when clicking on a link
            const mobileLinks = mobileNav.querySelectorAll('a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileNav.classList.remove('active');
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                });
            });
        }
        
        // User dropdown functionality
        const userIcon = document.querySelector('.user-icon');
        const dropdownMenu = document.querySelector('.dropdown-menu');
        
        if (userIcon && dropdownMenu) {
            userIcon.addEventListener('click', function(e) {
                e.preventDefault();
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userIcon.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
        
        // Newsletter submission via button click (avoids native form submit interference)
        const newsletterForm = document.getElementById('newsletterForm');
        const newsletterBtn = document.getElementById('newsletterSubmit');
        if (newsletterForm && newsletterBtn) {
            newsletterBtn.addEventListener('click', function(e) {
                const emailInput = document.getElementById('newsletterEmail');
                if (!emailInput) return;
                const email = String(emailInput.value || '').trim();

                // Use browser validity check if available, else fall back to regex
                const isValid = email && (typeof emailInput.checkValidity === 'function' ? emailInput.checkValidity() : validateEmail(email));

                if (isValid) {
                    // Send to server to save in database
                    const name = String(document.getElementById('newsletterName')?.value || '').trim();

                    const payload = new URLSearchParams();
                    payload.append('email', email);
                    if (name) payload.append('name', name);

                    const subUrl = '<?php echo SITE_URL; ?>subscribe-newsletter.php';
                    console.debug('Subscribing', subUrl, payload.toString());
                    fetch(subUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload.toString()
                    }).then(r => {
                        if (!r.ok) {
                            return r.text().then(txt => { throw new Error('HTTP ' + r.status + ': ' + txt); });
                        }
                        return r.json();
                    }).then(data => {
                        if (data && data.success) {
                            showNotification(data.message || 'Thank you for subscribing!', 'success');
                            newsletterForm.reset();
                        } else {
                            showNotification(data.message || 'Subscription failed.', 'error');
                        }
                    }).catch(err => {
                        console.error('Subscribe error', err);
                        showNotification('Network error. Please try again later.', 'error');
                    });
                } else {
                    const message = (emailInput && emailInput.validationMessage) ? emailInput.validationMessage : 'Please enter a valid email address.';
                    showNotification(message, 'error');
                }
            });
        }
        
        // Show cart count on all pages (call with server-side cart count)
        <?php
        $server_cart_count = 0;
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $it) {
                if (is_array($it) && isset($it['quantity'])) {
                    $server_cart_count += intval($it['quantity']);
                } else {
                    $server_cart_count += 1;
                }
            }
        }
        ?>
        if (typeof updateCartCount === 'function') updateCartCount(<?php echo intval($server_cart_count); ?>);
    });
    
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    function showNotification(message, type) {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
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
    
    // Note: dynamic `updateCartCount(count)` is provided by page scripts (e.g. `js/cart.js`).
    </script>
</body>
</html>