<?php
require_once 'includes/config.php';
$page_title = "Contact Us";
include 'includes/header.php';

$success = false;
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    // Validate inputs
    if(empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Save to database (contact_messages)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $subject, $message, $ip, $user_agent);
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
            } else {
                $error = 'Failed to save message. Please try again later.';
                error_log('contact.php: DB insert failed: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Failed to prepare database statement.';
            error_log('contact.php: prepare failed: ' . mysqli_error($conn));
        }
    }
}
?>

<div class="container">
    <h1 class="page-title">Contact Us</h1>
    
    <div class="contact-container">
        <div class="contact-info">
            <h2>Get in Touch</h2>
            <p>We'd love to hear from you! Whether you have questions about our products, need assistance with an order, or just want to share your candle experiences, our team is here to help.</p>
            
            <div class="contact-details">
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Our Location</h3>
                        <p>123 Candle Street<br>Patna, CA 90210<br>India</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Phone Number</h3>
                        <p>7488042794</p>
                        <p>Monday - Friday: 9am - 6pm PST</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Email Address</h3>
                        <p>info@candlehaven.com</p>
                        <p>support@candlehaven.com</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-text">
                        <h3>Business Hours</h3>
                        <p>Monday - Friday: 9am - 6pm</p>
                        <p>Saturday: 10am - 4pm</p>
                        <p>Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="contact-form-container">
            <?php if($success): ?>
                <div class="alert alert-success">
                    <h3>Thank You!</h3>
                    <p>Your message has been sent successfully. We'll get back to you within 24-48 hours.</p>
                    <a href="contact.php" class="btn">Send Another Message</a>
                </div>
            <?php else: ?>
                <h2>Send Us a Message</h2>
                
                <?php if($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="contact-form">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="faq-section">
        <h2>Frequently Asked Questions</h2>
        
        <div class="faq-item">
            <button class="faq-question">How long do your candles burn? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
                <p>Our candles have varying burn times depending on their size. Most of our standard candles (8-10 oz) burn for 40-50 hours. Larger candles (16-20 oz) can burn for 80-100 hours. Always trim the wick to ¼ inch before each burn for optimal performance.</p>
            </div>
        </div>
        
        <div class="faq-item">
            <button class="faq-question">Are your candles eco-friendly? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
                <p>Yes! We use 100% natural soy wax which is biodegradable and renewable. Our wicks are lead-free cotton, and all our fragrances are phthalate-free. We also use recyclable and reusable packaging materials.</p>
            </div>
        </div>
        
        <div class="faq-item">
            <button class="faq-question">What is your shipping policy? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
                <p>We offer free standard shipping on all orders over $50 within the continental United States. Orders are typically processed within 1-2 business days and delivered within 3-7 business days. We also offer expedited shipping options at checkout.</p>
            </div>
        </div>
        
        <div class="faq-item">
            <button class="faq-question">Do you offer international shipping? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
                <p>Yes, we ship to select international destinations. Shipping costs and delivery times vary by location. International orders may be subject to customs fees and import taxes, which are the responsibility of the customer.</p>
            </div>
        </div>
    </div>
</div>

<script>
// FAQ Accordion
document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            // Toggle active class
            this.classList.toggle('active');
            answer.classList.toggle('active');
            
            // Toggle icon
            if(this.classList.contains('active')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>