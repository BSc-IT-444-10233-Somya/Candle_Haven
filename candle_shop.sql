-- ============================================
-- Candle E-commerce Website Database Schema
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS candle_shop;
USE candle_shop;
-- ============================================
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (username, email, password)
VALUES (
    'admin',
    'admin@gmail.com',
    'admin123'
);


-- ============================================
-- Users Table
-- ============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    phone VARCHAR(20),
    is_admin BOOLEAN DEFAULT FALSE,
    newsletter_subscribed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- ============================================
-- Products Table
-- ============================================
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2),
    cost DECIMAL(10,2),
    image_url VARCHAR(255),
    category VARCHAR(50),
    scent VARCHAR(50),
    burn_time_hours INT,
    weight_grams INT,
    dimensions VARCHAR(50),
    material VARCHAR(50),
    wick_type VARCHAR(50),
    in_stock INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_scent (scent),
    INDEX idx_price (price),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active)
);

-- ============================================
-- Product Images Table (for multiple images)
-- ============================================
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id)
);

-- ============================================
-- Categories Table
-- ============================================
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent_id (parent_id),
    INDEX idx_active (is_active)
);

-- ============================================
-- Orders Table
-- ============================================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    subtotal_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_address TEXT NOT NULL,
    billing_address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(50) DEFAULT 'India',
    phone VARCHAR(20),
    email VARCHAR(100),
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    tracking_number VARCHAR(100),
    shipping_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- Order Items Table
-- ============================================
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(100) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

-- ============================================
-- Cart Table (for persistent cart)
-- ============================================
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
);

-- ============================================
-- Reviews Table
-- ============================================
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(100),
    comment TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product_id (product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_approved (is_approved),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- Wishlist Table
-- ============================================
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    INDEX idx_user_id (user_id)
);

-- ============================================
-- Coupons/Discounts Table
-- ============================================
CREATE TABLE coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0.00,
    max_discount_amount DECIMAL(10,2),
    usage_limit INT DEFAULT NULL,
    usage_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
);

-- ============================================
-- Contact Messages Table
-- ============================================
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- Newsletter Subscribers Table
-- ============================================
CREATE TABLE newsletter_subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_active (is_active)
);

-- ============================================
-- Shipping Methods Table
-- ============================================
CREATE TABLE shipping_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    cost DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0.00,
    max_order_amount DECIMAL(10,2) DEFAULT NULL,
    estimated_days VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);

-- ============================================
-- Tax Rates Table
-- ============================================
CREATE TABLE tax_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    state VARCHAR(50) NOT NULL,
    rate DECIMAL(5,4) NOT NULL COMMENT 'Tax rate as decimal (e.g., 0.0825 for 8.25%)',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_state (state),
    INDEX idx_active (is_active)
);

-- ============================================
-- Pages Table (for CMS)
-- ============================================
CREATE TABLE pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    content LONGTEXT,
    meta_title VARCHAR(200),
    meta_description VARCHAR(500),
    meta_keywords VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
);

-- ============================================
-- Settings Table
-- ============================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json', 'textarea') DEFAULT 'text',
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
);

-- ============================================
-- Activity Log Table
-- ============================================
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- Product Categories (Many-to-Many)
-- ============================================
CREATE TABLE product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);


-- ============================================
-- Password Reset Table (for OTP-based password recovery)
-- ============================================
-- Run this SQL to add password reset functionality to your database

CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    is_used BOOLEAN DEFAULT FALSE,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_used (is_used)
);

-- Optional: Create an index for cleanup queries
CREATE INDEX idx_created_at ON password_resets(created_at);

-- Note: OTP codes are hashed in the database for security
-- Verification tokens are also hashed before storage
-- The actual OTP is only sent via email and not stored in plain text


-- ============================================
-- Insert Sample Data
-- ============================================

-- Insert Admin User
-- Password: admin123 (hashed)
INSERT INTO users (username, email, password, first_name, last_name, is_admin) VALUES
('admin', 'admin@candlehaven.com', '$2y$10$YourHashedPasswordHere', 'Admin', 'User', 1);

-- Insert Regular User
-- Password: user123 (hashed)
INSERT INTO users (username, email, password, first_name, last_name, address, city, state, zip_code, phone) VALUES
('john_doe', 'john@example.com', '$2y$10$YourHashedPasswordHere', 'John', 'Doe', '123 Main St', 'New York', 'NY', '10001', '555-123-4567');

-- Insert Categories
INSERT INTO categories (name, slug, description, parent_id, display_order) VALUES
('All Candles', 'all-candles', 'All our handcrafted candles', NULL, 1),
('Scented Candles', 'scented-candles', 'Candles with beautiful fragrances', 1, 2),
('Unscented Candles', 'unscented-candles', 'Pure candles without added fragrances', 1, 3),
('Aromatherapy', 'aromatherapy', 'Candles with essential oils for wellness', 1, 4),
('Seasonal', 'seasonal', 'Candles for different seasons and holidays', 1, 5),
('Best Sellers', 'best-sellers', 'Our most popular candles', NULL, 6),
('New Arrivals', 'new-arrivals', 'Latest additions to our collection', NULL, 7);

-- Insert Products
INSERT INTO products (name, slug, description, short_description, price, compare_price, category, scent, burn_time_hours, weight_grams, in_stock, is_featured, rating, review_count) VALUES
('Vanilla Dream Candle', 'vanilla-dream-candle', 'A soothing vanilla scented candle perfect for relaxation. Made with 100% natural soy wax and premium vanilla fragrance oil. This candle creates a warm and inviting atmosphere in any room.', 'Soothing vanilla scented candle', 24.99, 29.99, 'Scented', 'Vanilla', 45, 300, 50, 1, 4.8, 42),
('Lavender Fields Candle', 'lavender-fields-candle', 'Calming lavender fragrance to help you unwind after a long day. Infused with real lavender essential oils for aromatherapy benefits. Perfect for bedrooms and meditation spaces.', 'Calming lavender fragrance', 29.99, 34.99, 'Aromatherapy', 'Lavender', 50, 400, 30, 1, 4.9, 38),
('Ocean Breeze Candle', 'ocean-breeze-candle', 'Fresh oceanic scent that brings the beach to your home. Notes of sea salt, driftwood, and fresh linen create a clean, refreshing atmosphere. Ideal for bathrooms and living areas.', 'Fresh oceanic beach scent', 27.99, NULL, 'Scented', 'Ocean', 40, 350, 40, 1, 4.7, 29),
('Unscented Beeswax Candle', 'unscented-beeswax-candle', 'Natural beeswax candle with no added fragrances. Perfect for those with scent sensitivities or who prefer the natural honey aroma of pure beeswax. Each candle is hand-rolled for a rustic look.', 'Natural beeswax, no fragrances', 34.99, 39.99, 'Unscented', 'Natural', 60, 500, 25, 1, 4.6, 21),
('Cinnamon Spice Candle', 'cinnamon-spice-candle', 'Warm cinnamon aroma for cozy evenings. Combined with hints of clove and nutmeg, this candle creates the perfect autumnal atmosphere. Great for kitchens and dining areas.', 'Warm cinnamon spice aroma', 26.99, NULL, 'Seasonal', 'Cinnamon', 45, 300, 35, 1, 4.5, 33),
('Sandalwood Serenity', 'sandalwood-serenity', 'Earthy sandalwood scent with notes of amber and musk. Known for its grounding properties, this candle is perfect for yoga studios and spaces where you want to promote mindfulness.', 'Earthy sandalwood scent', 32.99, 37.99, 'Aromatherapy', 'Sandalwood', 55, 450, 20, 0, 4.8, 18),
('Citrus Sunrise', 'citrus-sunrise', 'Energizing blend of lemon, orange, and grapefruit. This bright, refreshing scent is perfect for mornings or when you need an energy boost. Made with citrus essential oils.', 'Energizing citrus blend', 23.99, NULL, 'Scented', 'Citrus', 35, 280, 45, 0, 4.4, 27),
('Fresh Linen Candle', 'fresh-linen-candle', 'Clean, crisp scent reminiscent of freshly laundered sheets. This allergen-friendly candle is perfect for creating a clean, inviting atmosphere in any room of your home.', 'Clean fresh linen scent', 25.99, NULL, 'Scented', 'Fresh Linen', 42, 320, 38, 0, 4.6, 31),
('Pumpkin Spice Delight', 'pumpkin-spice-delight', 'Seasonal favorite with pumpkin, cinnamon, and nutmeg. This limited edition candle captures the essence of fall and is perfect for Thanksgiving gatherings or cozy nights in.', 'Seasonal pumpkin spice', 28.99, NULL, 'Seasonal', 'Pumpkin Spice', 40, 300, 15, 1, 4.9, 45),
('Rose Garden Candle', 'rose-garden-candle', 'Romantic rose fragrance with hints of peony and jasmine. This elegant candle is perfect for creating a romantic atmosphere or adding a touch of luxury to your self-care routine.', 'Romantic rose fragrance', 31.99, 36.99, 'Scented', 'Rose', 48, 380, 28, 0, 4.7, 24);

-- Insert Product Images
INSERT INTO product_images (product_id, image_url, alt_text, is_primary, display_order) VALUES
(1, 'images/products/vanilla-candle.jpg', 'Vanilla Dream Candle', 1, 1),
(1, 'images/products/vanilla-candle-2.jpg', 'Vanilla Dream Candle burning', 0, 2),
(2, 'images/products/lavender-candle.jpg', 'Lavender Fields Candle', 1, 1),
(3, 'images/products/ocean-candle.jpg', 'Ocean Breeze Candle', 1, 1),
(4, 'images/products/beeswax-candle.jpg', 'Unscented Beeswax Candle', 1, 1),
(5, 'images/products/cinnamon-candle.jpg', 'Cinnamon Spice Candle', 1, 1);

-- Insert Product Categories (Many-to-Many)
INSERT INTO product_categories (product_id, category_id) VALUES
(1, 2), (1, 6), -- Vanilla Dream: Scented, Best Sellers
(2, 4), (2, 6), -- Lavender Fields: Aromatherapy, Best Sellers
(3, 2), (3, 7), -- Ocean Breeze: Scented, New Arrivals
(4, 3), -- Beeswax: Unscented
(5, 5), (5, 6), -- Cinnamon Spice: Seasonal, Best Sellers
(6, 4), -- Sandalwood: Aromatherapy
(7, 2), (7, 7), -- Citrus Sunrise: Scented, New Arrivals
(8, 2), -- Fresh Linen: Scented
(9, 5), (9, 6), -- Pumpkin Spice: Seasonal, Best Sellers
(10, 2); -- Rose Garden: Scented

-- Insert Shipping Methods
INSERT INTO shipping_methods (name, description, cost, min_order_amount, estimated_days) VALUES
('Standard Shipping', 'Regular shipping via ground delivery', 5.99, 0.00, '3-7 business days'),
('Free Shipping', 'Free shipping on orders over $50', 0.00, 50.00, '5-10 business days'),
('Express Shipping', 'Priority 2-day shipping', 14.99, 0.00, '2 business days'),
('Overnight Shipping', 'Next day delivery', 24.99, 0.00, '1 business day');

-- Insert Tax Rates
INSERT INTO tax_rates (state, rate) VALUES
('AL', 0.0400), ('AK', 0.0000), ('AZ', 0.0560), ('AR', 0.0650),
('CA', 0.0725), ('CO', 0.0290), ('CT', 0.0635), ('DE', 0.0000),
('FL', 0.0600), ('GA', 0.0400), ('HI', 0.0400), ('ID', 0.0600),
('IL', 0.0625), ('IN', 0.0700), ('IA', 0.0600), ('KS', 0.0650),
('KY', 0.0600), ('LA', 0.0445), ('ME', 0.0550), ('MD', 0.0600),
('MA', 0.0625), ('MI', 0.0600), ('MN', 0.0688), ('MS', 0.0700),
('MO', 0.0423), ('MT', 0.0000), ('NE', 0.0550), ('NV', 0.0685),
('NH', 0.0000), ('NJ', 0.0663), ('NM', 0.0513), ('NY', 0.0400),
('NC', 0.0475), ('ND', 0.0500), ('OH', 0.0575), ('OK', 0.0450),
('OR', 0.0000), ('PA', 0.0600), ('RI', 0.0700), ('SC', 0.0600),
('SD', 0.0450), ('TN', 0.0700), ('TX', 0.0625), ('UT', 0.0610),
('VT', 0.0600), ('VA', 0.0530), ('WA', 0.0650), ('WV', 0.0600),
('WI', 0.0500), ('WY', 0.0400);

-- Insert Coupons
INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, usage_limit, valid_from, valid_until) VALUES
('WELCOME10', 'Welcome discount for new customers', 'percentage', 10.00, 25.00, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
('CANDLE20', '20% off all candles', 'percentage', 20.00, 40.00, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('FREESHIP', 'Free shipping on any order', 'fixed', 0.00, 0.00, 200, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY)),
('SAVE5', '$5 off your order', 'fixed', 5.00, 30.00, NULL, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY));

-- Insert Sample Orders
INSERT INTO orders (order_number, user_id, total_amount, subtotal_amount, shipping_amount, tax_amount, shipping_address, city, state, zip_code, payment_method, payment_status, status) VALUES
('ORD-10001', 2, 54.97, 48.98, 5.99, 0.00, '123 Main St', 'New York', 'NY', '10001', 'credit_card', 'paid', 'delivered'),
('ORD-10002', 2, 89.97, 83.98, 5.99, 0.00, '123 Main St', 'New York', 'NY', '10001', 'paypal', 'paid', 'shipped');

-- Insert Order Items
INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES
(1, 1, 'Vanilla Dream Candle', 24.99, 1, 24.99),
(1, 5, 'Cinnamon Spice Candle', 26.99, 1, 26.99),
(2, 2, 'Lavender Fields Candle', 29.99, 2, 59.98),
(2, 3, 'Ocean Breeze Candle', 27.99, 1, 27.99);

-- Insert Reviews
INSERT INTO reviews (product_id, user_id, rating, title, comment, is_approved) VALUES
(1, 2, 5, 'Amazing Vanilla Scent!', 'This candle smells exactly like vanilla bean. It burns evenly and lasts a long time. Will definitely buy again!', 1),
(1, NULL, 4, 'Good but wick could be better', 'Love the scent but the wick sometimes tunnels. Otherwise a great product.', 1),
(2, 2, 5, 'Perfect for Relaxation', 'The lavender scent is so calming. I light this every evening before bed and sleep like a baby.', 1),
(3, NULL, 5, 'Fresh and Clean', 'Makes my whole apartment smell like the ocean. Love it!', 1);

-- Insert Pages
INSERT INTO pages (title, slug, content, meta_title, meta_description, is_active) VALUES
('About Us', 'about', '<h2>Our Story</h2><p>Candle Haven began as a small passion project...</p>', 'About Candle Haven', 'Learn about our story and commitment to quality candles', 1),
('Shipping Policy', 'shipping', '<h2>Shipping Information</h2><p>We offer several shipping options...</p>', 'Shipping Policy', 'Information about our shipping methods and delivery times', 1),
('Returns & Exchanges', 'returns', '<h2>Return Policy</h2><p>We accept returns within 30 days...</p>', 'Return Policy', 'Learn about our return and exchange process', 1),
('Privacy Policy', 'privacy', '<h2>Privacy Policy</h2><p>Your privacy is important to us...</p>', 'Privacy Policy', 'Read our privacy policy and data protection practices', 1);

-- Insert Settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_name', 'Candle Haven', 'text', 'general', 'Website name'),
('site_email', 'info@candlehaven.com', 'text', 'general', 'Default email address'),
('currency', 'USD', 'text', 'general', 'Default currency'),
('currency_symbol', '$', 'text', 'general', 'Currency symbol'),
('free_shipping_threshold', '50', 'number', 'shipping', 'Order amount for free shipping'),
('default_shipping_cost', '5.99', 'number', 'shipping', 'Default shipping cost'),
('tax_enabled', '1', 'boolean', 'tax', 'Enable/disable tax calculation'),
('default_tax_rate', '0.0825', 'number', 'tax', 'Default tax rate if state not specified'),
('contact_email', 'contact@candlehaven.com', 'text', 'contact', 'Contact form recipient email'),
('facebook_url', 'https://facebook.com/candlehaven', 'text', 'social', 'Facebook page URL'),
('instagram_url', 'https://instagram.com/candlehaven', 'text', 'social', 'Instagram profile URL'),
('pinterest_url', 'https://pinterest.com/candlehaven', 'text', 'social', 'Pinterest profile URL');

-- Insert Newsletter Subscribers
INSERT INTO newsletter_subscribers (email, name, is_active) VALUES
('subscriber1@example.com', 'Jane Smith', 1),
('subscriber2@example.com', 'Bob Johnson', 1);

-- ============================================
-- Views for Reporting
-- ============================================

-- View for product sales
CREATE VIEW product_sales AS
SELECT 
    p.id,
    p.name,
    p.sku,
    SUM(oi.quantity) as total_sold,
    SUM(oi.subtotal) as total_revenue,
    AVG(r.rating) as average_rating,
    COUNT(r.id) as review_count
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
GROUP BY p.id;

-- View for monthly sales
CREATE VIEW monthly_sales AS
SELECT 
    DATE_FORMAT(o.created_at, '%Y-%m') as month,
    COUNT(DISTINCT o.id) as order_count,
    SUM(o.total_amount) as total_revenue,
    AVG(o.total_amount) as avg_order_value
FROM orders o
WHERE o.status NOT IN ('cancelled', 'refunded')
GROUP BY DATE_FORMAT(o.created_at, '%Y-%m');

-- View for customer orders
CREATE VIEW customer_orders AS
SELECT 
    u.id as customer_id,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    u.email,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_spent,
    MAX(o.created_at) as last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id AND o.status NOT IN ('cancelled', 'refunded')
WHERE u.is_admin = 0
GROUP BY u.id;

-- ============================================
-- Stored Procedures
-- ============================================

-- Procedure to update product rating
DELIMITER //
CREATE PROCEDURE UpdateProductRating(IN product_id INT)
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE review_count INT;
    
    SELECT AVG(rating), COUNT(*)
    INTO avg_rating, review_count
    FROM reviews
    WHERE product_id = product_id AND is_approved = 1;
    
    UPDATE products 
    SET rating = COALESCE(avg_rating, 0), 
        review_count = COALESCE(review_count, 0)
    WHERE id = product_id;
END//
DELIMITER ;

-- Procedure to get low stock products
DELIMITER //
CREATE PROCEDURE GetLowStockProducts(IN threshold INT)
BEGIN
    SELECT 
        id,
        name,
        in_stock,
        low_stock_threshold,
        CASE 
            WHEN in_stock = 0 THEN 'Out of Stock'
            WHEN in_stock <= low_stock_threshold THEN 'Low Stock'
            ELSE 'In Stock'
        END as stock_status
    FROM products
    WHERE in_stock <= threshold
    ORDER BY in_stock ASC;
END//
DELIMITER ;

-- Procedure to calculate order total
DELIMITER //
CREATE PROCEDURE CalculateOrderTotal(
    IN subtotal DECIMAL(10,2),
    IN shipping_state VARCHAR(50),
    IN shipping_method_id INT,
    IN coupon_code VARCHAR(50),
    OUT total DECIMAL(10,2),
    OUT tax_amount DECIMAL(10,2),
    OUT shipping_cost DECIMAL(10,2),
    OUT discount_amount DECIMAL(10,2)
)
BEGIN
    DECLARE tax_rate DECIMAL(5,4);
    DECLARE coupon_discount DECIMAL(10,2);
    DECLARE coupon_type ENUM('percentage', 'fixed');
    DECLARE shipping_method_cost DECIMAL(10,2);
    
    -- Get tax rate
    SELECT COALESCE(rate, (SELECT setting_value FROM settings WHERE setting_key = 'default_tax_rate'))
    INTO tax_rate
    FROM tax_rates 
    WHERE state = shipping_state AND is_active = 1;
    
    -- Get shipping cost
    SELECT cost 
    INTO shipping_method_cost
    FROM shipping_methods 
    WHERE id = shipping_method_id AND is_active = 1;
    
    -- Get coupon discount
    SELECT discount_value, discount_type
    INTO coupon_discount, coupon_type
    FROM coupons 
    WHERE code = coupon_code 
        AND is_active = 1
        AND (valid_from IS NULL OR valid_from <= CURDATE())
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        AND (usage_limit IS NULL OR usage_count < usage_limit)
        AND min_order_amount <= subtotal;
    
    -- Calculate tax
    SET tax_amount = subtotal * COALESCE(tax_rate, 0);
    
    -- Calculate shipping
    SET shipping_cost = COALESCE(shipping_method_cost, 0);
    
    -- Calculate discount
    IF coupon_discount IS NOT NULL THEN
        IF coupon_type = 'percentage' THEN
            SET discount_amount = subtotal * (coupon_discount / 100);
        ELSE
            SET discount_amount = coupon_discount;
        END IF;
    ELSE
        SET discount_amount = 0;
    END IF;
    
    -- Calculate total
    SET total = subtotal + tax_amount + shipping_cost - discount_amount;
END//
DELIMITER ;

-- ============================================
-- Triggers
-- ============================================

-- Trigger to update stock when order is placed
DELIMITER //
CREATE TRIGGER UpdateStockAfterOrder
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET in_stock = in_stock - NEW.quantity
    WHERE id = NEW.product_id;
END//
DELIMITER ;

-- Trigger to update stock when order is cancelled
DELIMITER //
CREATE TRIGGER RestoreStockAfterCancellation
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE products p
        JOIN order_items oi ON p.id = oi.product_id
        SET p.in_stock = p.in_stock + oi.quantity
        WHERE oi.order_id = NEW.id;
    END IF;
END//
DELIMITER ;

-- Trigger to log order status changes
DELIMITER //
CREATE TRIGGER LogOrderStatusChange
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO activity_log (user_id, action, details, created_at)
        VALUES (
            NEW.user_id, 
            'order_status_change',
            CONCAT('Order ', NEW.order_number, ' changed from ', OLD.status, ' to ', NEW.status),
            NOW()
        );
    END IF;
END//
DELIMITER ;

-- Trigger to generate order number
DELIMITER //
CREATE TRIGGER GenerateOrderNumber
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE next_num INT;
    
    IF NEW.order_number IS NULL THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, 5) AS UNSIGNED)), 10000) + 1
        INTO next_num
        FROM orders;
        
        SET NEW.order_number = CONCAT('ORD-', LPAD(next_num, 5, '0'));
    END IF;
END//
DELIMITER ;

-- ============================================
-- Indexes for Performance
-- ============================================

-- Add additional indexes for performance
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_products_stock ON products(in_stock, is_active);
CREATE INDEX idx_order_items_order_product ON order_items(order_id, product_id);
CREATE INDEX idx_reviews_product_rating ON reviews(product_id, rating, is_approved);

-- ============================================
-- User Permissions
-- ============================================

-- Create database user (run this separately)
-- CREATE USER 'candle_user'@'localhost' IDENTIFIED BY 'secure_password_123';
-- GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON candle_shop.* TO 'candle_user'@'localhost';
-- FLUSH PRIVILEGES;