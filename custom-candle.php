<?php
require_once 'includes/config.php';
$page_title = "Customize Your Candle";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    // Get form data
    $candle_type = sanitize_input($_POST['candle_type']);
    $wax_type = sanitize_input($_POST['wax_type']);
    $size = sanitize_input($_POST['size']);
    $wick_type = sanitize_input($_POST['wick_type']);
    $wick_color = sanitize_input($_POST['wick_color']);
    $fragrances = isset($_POST['fragrances']) ? $_POST['fragrances'] : [];
    $custom_scent = sanitize_input($_POST['custom_scent']);
    $color = sanitize_input($_POST['color']);
    $jar_style = sanitize_input($_POST['jar_style']);
    $label_text = sanitize_input($_POST['label_text']);
    $label_color = sanitize_input($_POST['label_color']);
    $label_font = sanitize_input($_POST['label_font']);
    $quantity = intval($_POST['quantity']);
    
    // Calculate price
    $base_price = 0;
    
    // Base price based on candle type
    $prices = [
        'container' => 12.99,
        'pillar' => 14.99,
        'votive' => 8.99,
        'tea-light' => 4.99,
        'jar' => 16.99
    ];
    $base_price = $prices[$candle_type] ?? 12.99;
    
    // Add size multiplier
    $size_multipliers = [
        'small' => 1.0,
        'medium' => 1.5,
        'large' => 2.0,
        'x-large' => 2.5
    ];
    $size_multiplier = $size_multipliers[$size] ?? 1.0;
    
    // Add wax type premium
    $wax_premiums = [
        'soy' => 0,
        'beeswax' => 5.00,
        'coconut' => 3.50,
        'paraffin' => -2.00
    ];
    $wax_premium = $wax_premiums[$wax_type] ?? 0;
    
    // Calculate fragrance cost (first fragrance included, additional $2 each)
    $fragrance_count = count($fragrances);
    $fragrance_cost = max(0, ($fragrance_count - 1)) * 2.00;
    
    // Add label customization cost
    $label_cost = (!empty($label_text) && $label_text != "Your Name/Message") ? 3.00 : 0;
    
    // Convert USD-based template prices to site currency (INR) if conversion rate defined
    $rate = defined('USD_TO_INR') ? USD_TO_INR : 1;
    $base_price = $base_price * $rate;
    $wax_premium = $wax_premium * $rate;
    $fragrance_cost = $fragrance_cost * $rate;
    $label_cost = $label_cost * $rate;

    // Calculate total price (now in site currency)
    $total_price = ($base_price * $size_multiplier) + $wax_premium + $fragrance_cost + $label_cost;
    $total_price = round($total_price * $quantity, 2);
    
    // Create custom candle description
    $description = "Custom $size " . ucfirst($wax_type) . " Wax $candle_type Candle";
    if (!empty($fragrances)) {
        $description .= " with " . implode(", ", $fragrances) . " fragrance";
        if ($fragrance_count > 1) $description .= " blend";
    }
    if ($custom_scent) {
        $description .= " (Custom: $custom_scent)";
    }
    $description .= ". $wick_color $wick_type wick";
    if ($color != "natural") {
        $description .= " in $color color";
    }
    if ($jar_style != "none") {
        $description .= " in $jar_style jar";
    }
    if ($label_text && $label_text != "Your Name/Message") {
        $description .= ". Label: \"$label_text\" in $label_font font";
    }
    
    // Generate a unique ID for the custom candle
    $custom_id = 'CUSTOM_' . time() . '_' . rand(1000, 9999);
    
    // Add to cart
    $_SESSION['cart'][$custom_id] = [
        'type' => 'custom',
        'name' => "Custom Candle: " . substr($description, 0, 50) . "...",
        'description' => $description,
        'price' => $total_price / $quantity,
        'quantity' => $quantity,
        'details' => [
            'candle_type' => $candle_type,
            'wax_type' => $wax_type,
            'size' => $size,
            'wick_type' => $wick_type,
            'wick_color' => $wick_color,
            'fragrances' => $fragrances,
            'custom_scent' => $custom_scent,
            'color' => $color,
            'jar_style' => $jar_style,
            'label_text' => $label_text,
            'label_color' => $label_color,
            'label_font' => $label_font,
            'custom_id' => $custom_id
        ]
    ];
    
    // Success message
    $success_message = "Your custom candle has been added to cart!";
    // If AJAX request, return JSON and exit
    if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
        header('Content-Type: application/json');
        $cart_count = 0;
        foreach ($_SESSION['cart'] as $it) {
            if (isset($it['quantity'])) $cart_count += $it['quantity'];
        }
        echo json_encode([
            'success' => true,
            'cartCount' => $cart_count,
            'message' => $success_message
        ]);
        exit;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <h1 class="page-title">Design Your Custom Candle</h1>
    <p class="text-center mb-40">Create a unique, personalized candle that reflects your style and preferences. Follow the steps below to design your perfect candle.</p>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <a href="cart.php" class="btn btn-small" style="margin-left: 20px;">View Cart</a>
            <a href="custom-candle.php" class="btn btn-small btn-secondary">Create Another</a>
        </div>
    <?php endif; ?>
    
    <div class="custom-candle-container">
        <div class="custom-candle-form">
            <form method="POST" action="" id="customCandleForm">
                
                <!-- Step 1: Candle Type -->
                <div class="customization-step active" id="step1">
                    <h2><span class="step-number">1</span> Choose Your Candle Type</h2>
                    <p class="step-description">Select the style of candle you want to create</p>
                    
                    <div class="option-grid">
                            <div class="option-card" data-value="jar">
                            <div class="option-image">
                                <i class="fas fa-jar"></i>
                            </div>
                            <h3>Jar Candle</h3>
                            <p>Classic candle in a reusable glass jar</p>
                            <div class="option-price">From <?php echo format_price(16.99 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                        </div>
                        
                        <div class="option-card" data-value="container">
                            <div class="option-image">
                                <i class="fas fa-mug-hot"></i>
                            </div>
                            <h3>Container Candle</h3>
                            <p>Poured into decorative containers</p>
                            <div class="option-price">From <?php echo format_price(12.99 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                        </div>
                        
                        <div class="option-card" data-value="pillar">
                            <div class="option-image">
                                <i class="fas fa-cube"></i>
                            </div>
                            <h3>Pillar Candle</h3>
                            <p>Standalone candle without container</p>
                            <div class="option-price">From <?php echo format_price(14.99 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                        </div>
                        
                        <div class="option-card" data-value="votive">
                            <div class="option-image">
                                <i class="fas fa-fire"></i>
                            </div>
                            <h3>Votive Candle</h3>
                            <p>Small candle for votive holders</p>
                            <div class="option-price">From <?php echo format_price(8.99 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                        </div>
                        
                        <div class="option-card" data-value="tea-light">
                            <div class="option-image">
                                <i class="fas fa-circle"></i>
                            </div>
                            <h3>Tea Light</h3>
                            <p>Small candles in aluminum cups</p>
                            <div class="option-price">From <?php echo format_price(4.99 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="candle_type" id="candle_type" value="jar">
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-next" data-next="step2">Next: Wax Type <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 2: Wax Type -->
                <div class="customization-step" id="step2">
                    <h2><span class="step-number">2</span> Choose Wax Type</h2>
                    <p class="step-description">Select the type of wax for your candle</p>
                    
                    <div class="option-grid">
                        <div class="option-card" data-value="soy">
                            <div class="option-image">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <h3>Soy Wax</h3>
                            <p>Natural, clean-burning, eco-friendly</p>
                            <div class="option-features">
                                <span><i class="fas fa-check"></i> 100% Natural</span>
                                <span><i class="fas fa-check"></i> Clean Burn</span>
                            </div>
                        </div>
                        
                        <div class="option-card" data-value="beeswax">
                            <div class="option-image">
                                <i class="fas fa-honey-pot"></i>
                            </div>
                            <h3>Beeswax</h3>
                            <p>Natural honey scent, long burn time</p>
                            <div class="option-price-premium">+<?php echo format_price(5.00 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                            <div class="option-features">
                                <span><i class="fas fa-check"></i> Natural Honey Scent</span>
                                <span><i class="fas fa-check"></i> Longest Burn</span>
                            </div>
                        </div>
                        
                        <div class="option-card" data-value="coconut">
                            <div class="option-image">
                                <i class="fas fa-pagelines"></i>
                            </div>
                            <h3>Coconut Wax</h3>
                            <p>Excellent scent throw, creamy texture</p>
                            <div class="option-price-premium">+<?php echo format_price(3.50 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                            <div class="option-features">
                                <span><i class="fas fa-check"></i> Great Scent Throw</span>
                                <span><i class="fas fa-check"></i> Creamy Texture</span>
                            </div>
                        </div>
                        
                        <div class="option-card" data-value="paraffin">
                            <div class="option-image">
                                <i class="fas fa-industry"></i>
                            </div>
                            <h3>Paraffin Wax</h3>
                            <p>Traditional, affordable option</p>
                            <div class="option-price-discount">-<?php echo format_price(2.00 * (defined('USD_TO_INR') ? USD_TO_INR : 1)); ?></div>
                            <div class="option-features">
                                <span><i class="fas fa-check"></i> Affordable</span>
                                <span><i class="fas fa-check"></i> Traditional</span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="wax_type" id="wax_type" value="soy">
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step1"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-next" data-next="step3">Next: Size <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 3: Size -->
                <div class="customization-step" id="step3">
                    <h2><span class="step-number">3</span> Choose Size</h2>
                    <p class="step-description">Select the size of your candle</p>
                    
                    <div class="size-options">
                        <div class="size-option" data-value="small">
                            <div class="size-visual small"></div>
                            <h3>Small</h3>
                            <p>4-6 oz<br>20-30 hour burn time</p>
                            <div class="size-price">1.0x base price</div>
                        </div>
                        
                        <div class="size-option" data-value="medium">
                            <div class="size-visual medium"></div>
                            <h3>Medium</h3>
                            <p>8-10 oz<br>40-50 hour burn time</p>
                            <div class="size-price">1.5x base price</div>
                        </div>
                        
                        <div class="size-option" data-value="large">
                            <div class="size-visual large"></div>
                            <h3>Large</h3>
                            <p>12-14 oz<br>60-70 hour burn time</p>
                            <div class="size-price">2.0x base price</div>
                        </div>
                        
                        <div class="size-option" data-value="x-large">
                            <div class="size-visual x-large"></div>
                            <h3>X-Large</h3>
                            <p>16-20 oz<br>80-100 hour burn time</p>
                            <div class="size-price">2.5x base price</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="size" id="size" value="medium">
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step2"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-next" data-next="step4">Next: Wick <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 4: Wick Options -->
                <div class="customization-step" id="step4">
                    <h2><span class="step-number">4</span> Choose Wick</h2>
                    <p class="step-description">Select wick type and color</p>
                    
                    <div class="wick-options">
                        <div class="wick-section">
                            <h3>Wick Type</h3>
                            <div class="radio-options">
                                <label class="radio-option">
                                    <input type="radio" name="wick_type" value="cotton" checked>
                                    <span class="radio-checkmark"></span>
                                    <span class="radio-label">
                                        <strong>Cotton Wick</strong>
                                        <small>Natural, clean-burning</small>
                                    </span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="wick_type" value="wood">
                                    <span class="radio-checkmark"></span>
                                    <span class="radio-label">
                                        <strong>Wood Wick</strong>
                                        <small>Crackling sound, aesthetic</small>
                                    </span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="wick_type" value="eco">
                                    <span class="radio-checkmark"></span>
                                    <span class="radio-label">
                                        <strong>Eco Wick</strong>
                                        <small>Lead-free, cotton & paper</small>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="wick-section">
                            <h3>Wick Color</h3>
                            <div class="color-options">
                                <label class="color-option">
                                    <input type="radio" name="wick_color" value="natural" checked>
                                    <span class="color-swatch" style="background-color: #f5e8d0;"></span>
                                    <span class="color-name">Natural</span>
                                </label>
                                
                                <label class="color-option">
                                    <input type="radio" name="wick_color" value="black">
                                    <span class="color-swatch" style="background-color: #000000;"></span>
                                    <span class="color-name">Black</span>
                                </label>
                                
                                <label class="color-option">
                                    <input type="radio" name="wick_color" value="white">
                                    <span class="color-swatch" style="background-color: #ffffff; border: 1px solid #ddd;"></span>
                                    <span class="color-name">White</span>
                                </label>
                                
                                <label class="color-option">
                                    <input type="radio" name="wick_color" value="red">
                                    <span class="color-swatch" style="background-color: #d4a574;"></span>
                                    <span class="color-name">Brown</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step3"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-next" data-next="step5">Next: Fragrance <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 5: Fragrance -->
                <div class="customization-step" id="step5">
                    <h2><span class="step-number">5</span> Choose Fragrance</h2>
                    <p class="step-description">Select one or more fragrances (first scent included, additional scents +$2 each)</p>
                    
                    <div class="fragrance-options">
                        <div class="fragrance-categories">
                            <div class="fragrance-category active" data-category="all">All Scents</div>
                            <div class="fragrance-category" data-category="floral">Floral</div>
                            <div class="fragrance-category" data-category="fruity">Fruity</div>
                            <div class="fragrance-category" data-category="woody">Woody</div>
                            <div class="fragrance-category" data-category="fresh">Fresh</div>
                            <div class="fragrance-category" data-category="seasonal">Seasonal</div>
                        </div>
                        
                        <div class="fragrance-grid">
                            <?php
                            $fragrances = [
                                ['name' => 'Lavender', 'category' => 'floral', 'icon' => 'fas fa-spa'],
                                ['name' => 'Rose', 'category' => 'floral', 'icon' => 'fas fa-heart'],
                                ['name' => 'Jasmine', 'category' => 'floral', 'icon' => 'fas fa-leaf'],
                                ['name' => 'Vanilla', 'category' => 'sweet', 'icon' => 'fas fa-ice-cream'],
                                ['name' => 'Coconut', 'category' => 'fruity', 'icon' => 'fas fa-umbrella-beach'],
                                ['name' => 'Citrus', 'category' => 'fruity', 'icon' => 'fas fa-lemon'],
                                ['name' => 'Apple Cinnamon', 'category' => 'seasonal', 'icon' => 'fas fa-apple-alt'],
                                ['name' => 'Pumpkin Spice', 'category' => 'seasonal', 'icon' => 'fas fa-pumpkin'],
                                ['name' => 'Sandalwood', 'category' => 'woody', 'icon' => 'fas fa-tree'],
                                ['name' => 'Cedarwood', 'category' => 'woody', 'icon' => 'fas fa-mountain'],
                                ['name' => 'Ocean Breeze', 'category' => 'fresh', 'icon' => 'fas fa-water'],
                                ['name' => 'Fresh Linen', 'category' => 'fresh', 'icon' => 'fas fa-wind'],
                                ['name' => 'Coffee', 'category' => 'food', 'icon' => 'fas fa-coffee'],
                                ['name' => 'Cinnamon', 'category' => 'spicy', 'icon' => 'fas fa-pepper-hot'],
                                ['name' => 'Eucalyptus', 'category' => 'fresh', 'icon' => 'fas fa-leaf'],
                                ['name' => 'Bergamot', 'category' => 'fruity', 'icon' => 'fas fa-lemon']
                            ];
                            
                            foreach ($fragrances as $fragrance):
                            ?>
                            <label class="fragrance-option">
                                <input type="checkbox" name="fragrances[]" value="<?php echo $fragrance['name']; ?>">
                                <span class="fragrance-card">
                                    <i class="<?php echo $fragrance['icon']; ?>"></i>
                                    <span class="fragrance-name"><?php echo $fragrance['name']; ?></span>
                                    <span class="fragrance-category-badge"><?php echo $fragrance['category']; ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="custom-scent-option">
                        <h3>Custom Scent Request</h3>
                        <p>Have a specific fragrance in mind? Describe it below:</p>
                        <textarea name="custom_scent" placeholder="Describe your custom scent (e.g., 'Fresh rain on pine trees', 'Grandma's apple pie', 'Summer garden after rain')" rows="3"></textarea>
                        <small class="note">Note: Custom scents may require additional time and may have an extra cost.</small>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step4"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-next" data-next="step6">Next: Color <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 6: Color -->
                <div class="customization-step" id="step6">
                    <h2><span class="step-number">6</span> Choose Color</h2>
                    <p class="step-description">Select the color of your candle wax</p>
                    
                    <div class="color-selection">
                        <div class="color-options-grid">
                            <label class="color-option-large">
                                <input type="radio" name="color" value="natural" checked>
                                <div class="color-preview" style="background-color: #f5e8d0;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Natural</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="white">
                                <div class="color-preview" style="background-color: #ffffff; border: 2px solid #f0f0f0;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">White</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="cream">
                                <div class="color-preview" style="background-color: #fffdd0;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Cream</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="ivory">
                                <div class="color-preview" style="background-color: #fffff0;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Ivory</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="red">
                                <div class="color-preview" style="background-color: #c0392b;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Red</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="blue">
                                <div class="color-preview" style="background-color: #3498db;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Blue</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="green">
                                <div class="color-preview" style="background-color: #27ae60;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Green</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="purple">
                                <div class="color-preview" style="background-color: #9b59b6;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Purple</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="gold">
                                <div class="color-preview" style="background-color: #f1c40f;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Gold</span>
                            </label>
                            
                            <label class="color-option-large">
                                <input type="radio" name="color" value="silver">
                                <div class="color-preview" style="background-color: #bdc3c7;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="color-label">Silver</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="jar-style-option">
                        <h3>Jar Style (Optional)</h3>
                        <p>If you selected a jar or container candle, choose your jar style:</p>
                        <select name="jar_style" class="form-select">
                            <option value="none">No preference</option>
                            <option value="mason">Mason Jar</option>
                            <option value="apothecary">Apothecary Jar</option>
                            <option value="tumbler">Tumbler Glass</option>
                            <option value="ceramic">Ceramic Pot</option>
                            <option value="tin">Metal Tin</option>
                            <option value="vintage">Vintage Glass</option>
                        </select>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step5"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-next" data-next="step7">Next: Personalization <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 7: Personalization -->
                <div class="customization-step" id="step7">
                    <h2><span class="step-number">7</span> Personalize Your Candle</h2>
                    <p class="step-description">Add a custom label to your candle (+$3.00)</p>
                    
                    <div class="personalization-options">
                        <div class="label-preview-container">
                            <div class="label-preview" id="labelPreview">
                                <span id="labelTextPreview">Your Name/Message</span>
                            </div>
                            <div class="label-controls">
                                <div class="form-group">
                                    <label>Label Text (Max 30 characters)</label>
                                    <input type="text" name="label_text" id="labelText" placeholder="Your Name/Message" maxlength="30" value="Your Name/Message">
                                    <div class="char-count"><span id="charCount">0</span>/30</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Label Color</label>
                                    <select name="label_color" id="labelColor" class="form-select">
                                        <option value="gold">Gold</option>
                                        <option value="silver">Silver</option>
                                        <option value="black">Black</option>
                                        <option value="white">White</option>
                                        <option value="brown">Brown</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Font Style</label>
                                    <select name="label_font" id="labelFont" class="form-select">
                                        <option value="script">Script</option>
                                        <option value="serif">Serif</option>
                                        <option value="sans-serif">Sans-serif</option>
                                        <option value="monospace">Monospace</option>
                                        <option value="cursive">Cursive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="quantity-selection">
                            <h3>Quantity</h3>
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn minus" id="quantityMinus">-</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="10">
                                <button type="button" class="quantity-btn plus" id="quantityPlus">+</button>
                            </div>
                            <small class="note">Maximum 10 custom candles per order</small>
                        </div>
                    </div>
                    
                    <!-- Price Summary -->
                    <div class="price-summary" id="priceSummary">
                        <h3>Price Summary</h3>
                        <div class="summary-details">
                            <div class="summary-row">
                                <span>Base Price (Medium Jar):</span>
                                <span id="basePrice"><?php echo format_price(16.99); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Wax Premium:</span>
                                <span id="waxPremium"><?php echo format_price(0); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Additional Fragrances:</span>
                                <span id="fragranceCost"><?php echo format_price(0); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Label Personalization:</span>
                                <span id="labelCost"><?php echo format_price(0); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>Subtotal:</span>
                                <span id="subtotal"><?php echo format_price(16.99); ?></span>
                            </div>
                            <div class="summary-row final-total">
                                <span>Total (x<span id="quantityDisplay">1</span>):</span>
                                <span id="totalPrice"><?php echo format_price(16.99); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn btn-prev" data-prev="step6"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-add-to-cart">
                            <i class="fas fa-shopping-cart"></i> Add to Cart - <span id="finalPrice">$16.99</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Live Preview -->
        <div class="custom-candle-preview">
            <div class="preview-container">
                <h3>Live Preview</h3>
                <div class="candle-preview" id="candlePreview">
                    <div class="candle-jar" id="previewJar">
                        <div class="candle-wax" id="previewWax">
                            <div class="candle-wick" id="previewWick"></div>
                        </div>
                        <div class="candle-label" id="previewLabel">
                            <span>Your Name/Message</span>
                        </div>
                    </div>
                </div>
                
                <div class="preview-details">
                    <h4>Your Custom Candle</h4>
                    <ul id="previewDetails">
                        <li><strong>Type:</strong> <span id="previewType">Jar Candle</span></li>
                        <li><strong>Wax:</strong> <span id="previewWaxType">Soy Wax</span></li>
                        <li><strong>Size:</strong> <span id="previewSize">Medium (8-10 oz)</span></li>
                        <li><strong>Wick:</strong> <span id="previewWick">Natural Cotton</span></li>
                        <li><strong>Fragrance:</strong> <span id="previewFragrance">Unscented</span></li>
                        <li><strong>Color:</strong> <span id="previewColor">Natural</span></li>
                        <li><strong>Jar:</strong> <span id="previewJarStyle">Standard</span></li>
                    </ul>
                </div>
                
                <div class="preview-timeline">
                    <div class="timeline-item active">
                        <div class="timeline-icon">1</div>
                        <span>Type</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">2</div>
                        <span>Wax</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">3</div>
                        <span>Size</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">4</div>
                        <span>Wick</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">5</div>
                        <span>Scent</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">6</div>
                        <span>Color</span>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon">7</div>
                        <span>Label</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Candle FAQ -->
    <div class="custom-candle-faq">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <h3>How long does it take to make a custom candle?</h3>
                <p>Custom candles typically take 3-5 business days to produce before shipping. During peak seasons, please allow 5-7 business days.</p>
            </div>
            <div class="faq-item">
                <h3>Can I request a custom scent blend?</h3>
                <p>Yes! Use the custom scent description field to describe your desired fragrance. Our candle makers will contact you if they need more details.</p>
            </div>
            <div class="faq-item">
                <h3>Are custom candles returnable?</h3>
                <p>Due to their personalized nature, custom candles are not returnable unless there is a defect in craftsmanship.</p>
            </div>
            <div class="faq-item">
                <h3>Can I order bulk custom candles?</h3>
                <p>Yes! For orders of 25+ custom candles, please contact us for wholesale pricing and options.</p>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo SITE_URL; ?>js/custom-candle.js"></script>
<?php include 'includes/footer.php'; ?>