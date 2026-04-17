document.addEventListener('DOMContentLoaded', function() {
    // Price configuration
    const prices = {
        'jar': 16.99,
        'container': 12.99,
        'pillar': 14.99,
        'votive': 8.99,
        'tea-light': 4.99
    };

    const waxPremiums = {
        'soy': 0,
        'beeswax': 5.00,
        'coconut': 3.50,
        'paraffin': -2.00
    };

    const sizeMultipliers = {
        'small': 1.0,
        'medium': 1.5,
        'large': 2.0,
        'x-large': 2.5
    };

    const sizeDescriptions = {
        'small': 'Small (4-6 oz)',
        'medium': 'Medium (8-10 oz)',
        'large': 'Large (12-14 oz)',
        'x-large': 'X-Large (16-20 oz)'
    };

    // Client-side currency conversion (USD -> INR)
    const USD_TO_INR = 82.00; // update if needed
    const CURRENCY_SYMBOL = '₹';

    function formatPriceUSDToINR(amount) {
        const inr = (Number(amount) * USD_TO_INR);
        return CURRENCY_SYMBOL + inr.toFixed(2);
    }

    const wickDescriptions = {
        'cotton': { 'natural': 'Natural Cotton', 'black': 'Black Cotton', 'white': 'White Cotton', 'red': 'Brown Cotton' },
        'wood': { 'natural': 'Natural Wood', 'black': 'Black Wood', 'white': 'White Wood', 'red': 'Brown Wood' },
        'eco': { 'natural': 'Natural Eco', 'black': 'Black Eco', 'white': 'White Eco', 'red': 'Brown Eco' }
    };

    // Current selections
    let currentSelections = {
        candle_type: 'jar',
        wax_type: 'soy',
        size: 'medium',
        wick_type: 'cotton',
        wick_color: 'natural',
        color: 'natural',
        jar_style: 'none',
        label_text: 'Your Name/Message',
        label_color: 'gold',
        label_font: 'script',
        quantity: 1,
        fragrances: []
    };

    // DOM Elements
    const form = document.getElementById('customCandleForm');
    const previewJar = document.getElementById('previewJar');
    const previewWax = document.getElementById('previewWax');
    const previewWick = document.getElementById('previewWick');
    const previewLabel = document.getElementById('previewLabel');
    const labelTextPreview = document.getElementById('labelTextPreview');
    
    // Price elements
    const basePriceEl = document.getElementById('basePrice');
    const waxPremiumEl = document.getElementById('waxPremium');
    const fragranceCostEl = document.getElementById('fragranceCost');
    const labelCostEl = document.getElementById('labelCost');
    const subtotalEl = document.getElementById('subtotal');
    const totalPriceEl = document.getElementById('totalPrice');
    const finalPriceEl = document.getElementById('finalPrice');
    const quantityDisplay = document.getElementById('quantityDisplay');
    
    // Preview detail elements
    const previewType = document.getElementById('previewType');
    const previewWaxType = document.getElementById('previewWaxType');
    const previewSize = document.getElementById('previewSize');
    const previewWickEl = document.getElementById('previewWick');
    const previewFragrance = document.getElementById('previewFragrance');
    const previewColor = document.getElementById('previewColor');
    const previewJarStyle = document.getElementById('previewJarStyle');

    // Initialize
    updatePreview();
    updatePrice();
    setupEventListeners();
    setupStepNavigation();

    // Handle AJAX submit for custom candle form to update cart count without reload
    const customForm = document.getElementById('customCandleForm');
    if (customForm) {
        customForm.addEventListener('submit', function(e) {
            // If the form has a specific non-AJAX submit button, still intercept
            e.preventDefault();

            const fd = new FormData(customForm);
            fd.append('ajax', 'true');
            fd.append('add_to_cart', '1');

            fetch('custom-candle.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const cartCount = data.cartCount !== undefined ? data.cartCount : (data.cart_count !== undefined ? data.cart_count : 0);
                    if (typeof updateCartCount === 'function') updateCartCount(cartCount);
                    showNotification(data.message || 'Custom candle added to cart', 'success');
                } else {
                    showNotification(data.message || 'Error adding custom candle', 'error');
                }
            })
            .catch(err => {
                console.error('Custom candle add error', err);
                showNotification('Network error. Please try again.', 'error');
            });
        });
    }

    function setupEventListeners() {
        // Option card clicks
        document.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', function() {
                const step = this.closest('.customization-step').id;
                const value = this.getAttribute('data-value');
                
                // Remove active class from siblings
                this.parentElement.querySelectorAll('.option-card').forEach(c => {
                    c.classList.remove('active');
                });
                
                // Add active class to clicked card
                this.classList.add('active');
                
                // Update hidden input
                const input = document.querySelector(`#${step} input[type="hidden"]`);
                if (input) {
                    input.value = value;
                }
                
                // Update current selections
                if (step === 'step1') {
                    currentSelections.candle_type = value;
                    document.getElementById('candle_type').value = value;
                } else if (step === 'step2') {
                    currentSelections.wax_type = value;
                    document.getElementById('wax_type').value = value;
                } else if (step === 'step3') {
                    currentSelections.size = value;
                    document.getElementById('size').value = value;
                }
                
                updatePreview();
                updatePrice();
            });
        });

        // Size option clicks
        document.querySelectorAll('.size-option').forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                
                // Remove active class from siblings
                this.parentElement.querySelectorAll('.size-option').forEach(o => {
                    o.classList.remove('active');
                });
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Update selection
                currentSelections.size = value;
                document.getElementById('size').value = value;
                
                updatePreview();
                updatePrice();
            });
        });

        // Radio button changes
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const name = this.name;
                const value = this.value;
                
                currentSelections[name] = value;
                updatePreview();
                updatePrice();
            });
        });

        // Checkbox changes (fragrances)
        document.querySelectorAll('input[name="fragrances[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateFragrances();
                updatePreview();
                updatePrice();
            });
        });

        // Fragrance category filter
        document.querySelectorAll('.fragrance-category').forEach(category => {
            category.addEventListener('click', function() {
                const categoryName = this.getAttribute('data-category');
                
                // Update active category
                document.querySelectorAll('.fragrance-category').forEach(c => {
                    c.classList.remove('active');
                });
                this.classList.add('active');
                
                // Filter fragrance grid
                document.querySelectorAll('.fragrance-option').forEach(option => {
                    if (categoryName === 'all') {
                        option.style.display = 'block';
                    } else {
                        const fragranceCard = option.querySelector('.fragrance-card');
                        const categoryBadge = fragranceCard.querySelector('.fragrance-category-badge');
                        if (categoryBadge.textContent === categoryName) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                });
            });
        });

        // Label text input
        const labelTextInput = document.getElementById('labelText');
        if (labelTextInput) {
            labelTextInput.addEventListener('input', function() {
                const text = this.value.trim() || 'Your Name/Message';
                currentSelections.label_text = text;
                labelTextPreview.textContent = text;
                
                // Update character count
                const charCount = document.getElementById('charCount');
                if (charCount) {
                    charCount.textContent = this.value.length;
                }
                
                updatePrice();
            });
        }

        // Label color and font select
        document.getElementById('labelColor').addEventListener('change', function() {
            currentSelections.label_color = this.value;
            updatePreview();
        });

        document.getElementById('labelFont').addEventListener('change', function() {
            currentSelections.label_font = this.value;
            updatePreview();
        });

        // Jar style select
        document.querySelector('select[name="jar_style"]').addEventListener('change', function() {
            currentSelections.jar_style = this.value;
            updatePreview();
        });

        // Custom scent textarea
        document.querySelector('textarea[name="custom_scent"]').addEventListener('input', function() {
            updatePreview();
        });

        // Quantity controls
        const quantityInput = document.getElementById('quantity');
        const quantityMinus = document.getElementById('quantityMinus');
        const quantityPlus = document.getElementById('quantityPlus');

        if (quantityMinus && quantityPlus && quantityInput) {
            quantityMinus.addEventListener('click', function() {
                let value = parseInt(quantityInput.value);
                if (value > 1) {
                    quantityInput.value = value - 1;
                    currentSelections.quantity = value - 1;
                    updatePrice();
                }
            });

            quantityPlus.addEventListener('click', function() {
                let value = parseInt(quantityInput.value);
                if (value < 10) {
                    quantityInput.value = value + 1;
                    currentSelections.quantity = value + 1;
                    updatePrice();
                }
            });

            quantityInput.addEventListener('change', function() {
                let value = parseInt(this.value);
                if (value < 1) this.value = 1;
                if (value > 10) this.value = 10;
                currentSelections.quantity = parseInt(this.value);
                updatePrice();
            });
        }
    }

    function updateFragrances() {
        const selectedFragrances = [];
        document.querySelectorAll('input[name="fragrances[]"]:checked').forEach(checkbox => {
            selectedFragrances.push(checkbox.value);
        });
        currentSelections.fragrances = selectedFragrances;
    }

    function updatePreview() {
        // Update candle type in preview
        const candleTypes = {
            'jar': 'Jar Candle',
            'container': 'Container Candle',
            'pillar': 'Pillar Candle',
            'votive': 'Votive Candle',
            'tea-light': 'Tea Light'
        };
        previewType.textContent = candleTypes[currentSelections.candle_type] || 'Jar Candle';

        // Update wax type in preview
        const waxTypes = {
            'soy': 'Soy Wax',
            'beeswax': 'Beeswax',
            'coconut': 'Coconut Wax',
            'paraffin': 'Paraffin Wax'
        };
        previewWaxType.textContent = waxTypes[currentSelections.wax_type] || 'Soy Wax';

        // Update size in preview
        previewSize.textContent = sizeDescriptions[currentSelections.size] || 'Medium (8-10 oz)';

        // Update wick in preview
        const wickType = currentSelections.wick_type || 'cotton';
        const wickColor = currentSelections.wick_color || 'natural';
        previewWickEl.textContent = wickDescriptions[wickType][wickColor] || 'Natural Cotton';

        // Update wick preview
        const wickColors = {
            'natural': '#f5e8d0',
            'black': '#000000',
            'white': '#ffffff',
            'red': '#d4a574'
        };
        if (previewWick) {
            previewWick.style.backgroundColor = wickColors[wickColor] || '#f5e8d0';
            previewWick.style.borderColor = wickColor === 'white' ? '#ddd' : wickColors[wickColor];
        }

        // Update fragrance in preview
        if (currentSelections.fragrances.length > 0) {
            if (currentSelections.fragrances.length === 1) {
                previewFragrance.textContent = currentSelections.fragrances[0];
            } else {
                previewFragrance.textContent = currentSelections.fragrances[0] + ' +' + (currentSelections.fragrances.length - 1) + ' more';
            }
        } else {
            const customScent = document.querySelector('textarea[name="custom_scent"]').value.trim();
            if (customScent) {
                previewFragrance.textContent = 'Custom: ' + (customScent.length > 20 ? customScent.substring(0, 20) + '...' : customScent);
            } else {
                previewFragrance.textContent = 'Unscented';
            }
        }

        // Update color in preview
        const colorNames = {
            'natural': 'Natural',
            'white': 'White',
            'cream': 'Cream',
            'ivory': 'Ivory',
            'red': 'Red',
            'blue': 'Blue',
            'green': 'Green',
            'purple': 'Purple',
            'gold': 'Gold',
            'silver': 'Silver'
        };
        previewColor.textContent = colorNames[currentSelections.color] || 'Natural';

        // Update color in visual preview
        const colorValues = {
            'natural': '#f5e8d0',
            'white': '#ffffff',
            'cream': '#fffdd0',
            'ivory': '#fffff0',
            'red': '#c0392b',
            'blue': '#3498db',
            'green': '#27ae60',
            'purple': '#9b59b6',
            'gold': '#f1c40f',
            'silver': '#bdc3c7'
        };
        if (previewWax) {
            previewWax.style.backgroundColor = colorValues[currentSelections.color] || '#f5e8d0';
            previewWax.style.borderColor = currentSelections.color === 'white' ? '#ddd' : colorValues[currentSelections.color];
        }

        // Update jar style in preview
        const jarStyles = {
            'none': 'Standard',
            'mason': 'Mason Jar',
            'apothecary': 'Apothecary Jar',
            'tumbler': 'Tumbler Glass',
            'ceramic': 'Ceramic Pot',
            'tin': 'Metal Tin',
            'vintage': 'Vintage Glass'
        };
        previewJarStyle.textContent = jarStyles[currentSelections.jar_style] || 'Standard';

        // Update jar preview style
        if (previewJar) {
            previewJar.className = 'candle-jar';
            if (currentSelections.jar_style !== 'none') {
                previewJar.classList.add(`jar-${currentSelections.jar_style}`);
            }
        }

        // Update label preview
        if (previewLabel) {
            const labelColors = {
                'gold': '#f1c40f',
                'silver': '#bdc3c7',
                'black': '#2c3e50',
                'white': '#ffffff',
                'brown': '#8b4513'
            };
            
            const labelText = currentSelections.label_text || 'Your Name/Message';
            labelTextPreview.textContent = labelText;
            
            previewLabel.style.color = labelColors[currentSelections.label_color] || '#f1c40f';
            previewLabel.style.fontFamily = getFontFamily(currentSelections.label_font);
            
            // Show/hide label based on text
            if (labelText === 'Your Name/Message') {
                previewLabel.style.opacity = '0.5';
            } else {
                previewLabel.style.opacity = '1';
            }
        }

        // Update size preview
        if (previewJar) {
            previewJar.className = 'candle-jar ' + currentSelections.size;
        }
    }

    function getFontFamily(font) {
        const fonts = {
            'script': "'Brush Script MT', cursive",
            'serif': "'Times New Roman', serif",
            'sans-serif': "'Arial', sans-serif",
            'monospace': "'Courier New', monospace",
            'cursive': "'Comic Sans MS', cursive"
        };
        return fonts[font] || "'Brush Script MT', cursive";
    }

    function updatePrice() {
        // Get base price
        const basePrice = prices[currentSelections.candle_type] || 16.99;
        
        // Calculate size multiplier
        const sizeMultiplier = sizeMultipliers[currentSelections.size] || 1.5;
        
        // Calculate wax premium
        const waxPremium = waxPremiums[currentSelections.wax_type] || 0;
        
        // Calculate fragrance cost (first free, $2 each additional)
        const fragranceCount = currentSelections.fragrances.length;
        const fragranceCost = Math.max(0, (fragranceCount - 1)) * 2.00;
        
        // Calculate label cost ($3 if personalized)
        const labelText = currentSelections.label_text || 'Your Name/Message';
        const labelCost = (labelText !== 'Your Name/Message' && labelText.trim() !== '') ? 3.00 : 0;
        
        // Calculate subtotal for one candle
        const subtotal = (basePrice * sizeMultiplier) + waxPremium + fragranceCost + labelCost;
        
        // Calculate total for all candles
        const total = subtotal * currentSelections.quantity;
        
        // Update price display (converted to INR)
        basePriceEl.textContent = formatPriceUSDToINR(basePrice * sizeMultiplier);
        waxPremiumEl.textContent = waxPremium >= 0 ? '+' + formatPriceUSDToINR(waxPremium) : '-' + formatPriceUSDToINR(Math.abs(waxPremium));
        fragranceCostEl.textContent = fragranceCount > 1 ? '+' + formatPriceUSDToINR(fragranceCost) : CURRENCY_SYMBOL + '0.00';
        labelCostEl.textContent = labelCost > 0 ? '+' + formatPriceUSDToINR(labelCost) : CURRENCY_SYMBOL + '0.00';
        subtotalEl.textContent = formatPriceUSDToINR(subtotal);
        totalPriceEl.textContent = formatPriceUSDToINR(total);
        finalPriceEl.textContent = formatPriceUSDToINR(total);
        quantityDisplay.textContent = currentSelections.quantity;
    }

    function setupStepNavigation() {
        // Next buttons
        document.querySelectorAll('.btn-next').forEach(button => {
            button.addEventListener('click', function() {
                const nextStep = this.getAttribute('data-next');
                navigateToStep(nextStep);
            });
        });

        // Previous buttons
        document.querySelectorAll('.btn-prev').forEach(button => {
            button.addEventListener('click', function() {
                const prevStep = this.getAttribute('data-prev');
                navigateToStep(prevStep);
            });
        });
    }

    function navigateToStep(stepId) {
        // Hide all steps
        document.querySelectorAll('.customization-step').forEach(step => {
            step.classList.remove('active');
        });
        
        // Show target step
        const targetStep = document.getElementById(stepId);
        if (targetStep) {
            targetStep.classList.add('active');
            targetStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Update timeline
            updateTimeline(stepId);
        }
    }

    function updateTimeline(stepId) {
        const stepNumber = parseInt(stepId.replace('step', ''));
        const timelineItems = document.querySelectorAll('.timeline-item');
        
        timelineItems.forEach((item, index) => {
            item.classList.remove('active');
            if (index < stepNumber) {
                item.classList.add('active');
            }
        });
    }

    // Initialize first step as active
    document.querySelector('.option-card[data-value="jar"]').classList.add('active');
    document.querySelector('.size-option[data-value="medium"]').classList.add('active');
    
    // Initialize character count
    const labelTextInput = document.getElementById('labelText');
    const charCount = document.getElementById('charCount');
    if (labelTextInput && charCount) {
        charCount.textContent = labelTextInput.value.length;
    }
});