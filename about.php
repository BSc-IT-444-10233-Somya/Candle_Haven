<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body>  
<?php
require_once 'includes/config.php';
$page_title = "About Us";
include 'includes/header.php';
?>

<div class="container">
    <div class="about-section">
        <h1 class="page-title">About Candle Haven</h1>
        
        <div class="about-content">
            <div class="about-image">
                <img src="images/cherry-blossom.jpg" alt="Our candle making process">
            </div>
            
            <div class="about-text">
                <h2>Our Story</h2>
                <p>Founded in 2015, Candle Haven began as a small passion project in a home kitchen. What started as a hobby to create natural, eco-friendly candles for friends and family quickly grew into a beloved brand known for its quality craftsmanship and enchanting scents.</p>
                
                <p>Our founder, Sarah Miller, believed that candles should do more than just provide light—they should create experiences, evoke emotions, and transform spaces into sanctuaries of comfort and peace.</p>
                
                <h2>Our Philosophy</h2>
                <p>At Candle Haven, we're committed to sustainability and quality. We source only the finest natural ingredients, including:</p>
                
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> 100% natural soy wax</li>
                    <li><i class="fas fa-check"></i> Lead-free cotton wicks</li>
                    <li><i class="fas fa-check"></i> Phthalate-free fragrance oils</li>
                    <li><i class="fas fa-check"></i> Essential oils for aromatherapy blends</li>
                    <li><i class="fas fa-check"></i> Recyclable and reusable packaging</li>
                </ul>
                
                <h2>Our Process</h2>
                <p>Each Candle Haven product is hand-poured with care and attention to detail. Our process involves:</p>
                
                <div class="process-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Careful Formulation</h3>
                        <p>Expert blending of fragrances for optimal scent throw and longevity</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Precision Pouring</h3>
                        <p>Hand-poured at the perfect temperature to ensure smooth, even candles</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Quality Testing</h3>
                        <p>Each batch undergoes rigorous testing for burn time and scent performance</p>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3>Eco-Packaging</h3>
                        <p>Carefully packaged in sustainable materials for safe delivery</p>
                    </div>
                </div>
                
                <div class="mission-statement">
                    <h2>Our Mission</h2>
                    <p>To illuminate lives with candles that not only create beautiful ambiance but also contribute to a healthier planet. We believe in creating products that you can feel good about burning in your home.</p>
                </div>
            </div>
        </div>
        
        <div class="team-section">
            <h2>Meet Our Team</h2>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-image">
                        <img src="images/Sarah Miller.jpg" alt="Sarah Miller">
                    </div>
                    <h3>Sarah Miller</h3>
                    <p class="position">Founder & Master Chandler</p>
                    <p>With over 10 years of experience in candle making and fragrance blending</p>
                </div>
                <div class="team-member">
                    <div class="member-image">
                        <img src="images/Michael Chen.jpeg" alt="Michael Chen">
                    </div>
                    <h3>Michael Chen</h3>
                    <p class="position">Operations Manager</p>
                    <p>Ensures every candle meets our high standards of quality and sustainability</p>
                </div>
                <div class="team-member">
                    <div class="member-image">
                        <img src="images/Emily Rodriguez.jpg" alt="Emily Rodriguez">
                    </div>
                    <h3>Emily Rodriguez</h3>
                    <p class="position">Creative Director</p>
                    <p>Designs our beautiful packaging and develops new scent combinations</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>