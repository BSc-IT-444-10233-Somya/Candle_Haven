// Product Page Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Image Gallery
    const mainImage = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (thumbnails.length > 0) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const imageUrl = this.getAttribute('data-image');
                mainImage.src = imageUrl;
                
                // Update active thumbnail
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    // Quantity Selector
    const quantityInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    const maxQuantity = quantityInput ? parseInt(quantityInput.max) : 1;

    if (minusBtn && plusBtn && quantityInput) {
        minusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value > 1) {
                quantityInput.value = value - 1;
            }
        });

        plusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value < maxQuantity) {
                quantityInput.value = value + 1;
            }
        });

        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (value < 1) this.value = 1;
            if (value > maxQuantity) this.value = maxQuantity;
        });
    }

    // Product Tabs
    const tabHeaders = document.querySelectorAll('.tab-header');
    const tabContents = document.querySelectorAll('.tab-content');

    tabHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Update active tab header
            tabHeaders.forEach(h => h.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding tab content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === 'tab-' + tabId) {
                    content.classList.add('active');
                }
            });
        });
    });

    // Review Stars Selection
    const starsSelect = document.querySelector('.stars-select');
    const selectedRatingInput = document.getElementById('selectedRating');

    if (starsSelect && selectedRatingInput) {
        const stars = starsSelect.querySelectorAll('i');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                selectedRatingInput.value = rating;
                
                // Update stars display
                stars.forEach(s => {
                    const starRating = s.getAttribute('data-rating');
                    if (starRating <= rating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
        });
    }

    // Write Review Toggle
    const writeReviewBtn = document.getElementById('writeReviewBtn');
    const cancelReviewBtn = document.getElementById('cancelReviewBtn');
    const writeReviewForm = document.getElementById('writeReviewForm');

    if (writeReviewBtn && writeReviewForm) {
        writeReviewBtn.addEventListener('click', function() {
            writeReviewForm.style.display = 'block';
            this.style.display = 'none';
            writeReviewForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (cancelReviewBtn && writeReviewForm) {
        cancelReviewBtn.addEventListener('click', function() {
            writeReviewForm.style.display = 'none';
            if (writeReviewBtn) {
                writeReviewBtn.style.display = 'block';
            }
        });
    }

    // FAQ Accordion
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            // Toggle active class
            this.classList.toggle('active');
            answer.classList.toggle('active');
            
            // Toggle icon
            if (this.classList.contains('active')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
    });

    // Notify When Available
    const notifyMeBtn = document.getElementById('notifyMeBtn');
    if (notifyMeBtn) {
        notifyMeBtn.addEventListener('click', function() {
            if (!isLoggedIn()) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            }
            
            const productId = new URLSearchParams(window.location.search).get('id');
            
            // Show notification form
            const email = prompt('Enter your email to be notified when this product is back in stock:');
            if (email && validateEmail(email)) {
                fetch('notify-me.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('You will be notified when this product is back in stock!');
                    } else {
                        alert('Error: ' + (data.message || 'Unable to process request'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error. Please try again.');
                });
            }
        });
    }

    // Helper functions
    function isLoggedIn() {
        return document.body.classList.contains('logged-in');
    }

    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    // Product Image Zoom (optional)
    const mainImageContainer = document.querySelector('.main-image');
    if (mainImageContainer && mainImage) {
        mainImageContainer.addEventListener('mousemove', function(e) {
            if (!this.classList.contains('zoom-enabled')) return;
            
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const xPercent = (x / rect.width) * 100;
            const yPercent = (y / rect.height) * 100;
            
            mainImage.style.transformOrigin = `${xPercent}% ${yPercent}%`;
        });
        
        mainImageContainer.addEventListener('mouseenter', function() {
            this.classList.add('zoom-enabled');
            mainImage.style.transform = 'scale(1.5)';
        });
        
        mainImageContainer.addEventListener('mouseleave', function() {
            this.classList.remove('zoom-enabled');
            mainImage.style.transform = 'scale(1)';
        });
    }
});