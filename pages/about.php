<?php
// pages/about.php - About Us Page

$page_title = "About Us";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get company information from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'about_content'");
    $stmt->execute();
    $about_content = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_mission'");
    $stmt->execute();
    $company_mission = $stmt->fetchColumn() ?: "To provide quality products with exceptional customer service, making online shopping accessible and enjoyable for everyone.";
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_vision'");
    $stmt->execute();
    $company_vision = $stmt->fetchColumn() ?: "To become Nigeria's most trusted online marketplace, connecting people with products that enhance their lives.";
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'founded_year'");
    $stmt->execute();
    $founded_year = $stmt->fetchColumn() ?: '2020';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_values'");
    $stmt->execute();
    $company_values = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $about_content = '';
    $company_mission = "To provide quality products with exceptional customer service, making online shopping accessible and enjoyable for everyone.";
    $company_vision = "To become Nigeria's most trusted online marketplace, connecting people with products that enhance their lives.";
    $founded_year = '2020';
    $company_values = '';
}

// Define company values if not in database
if (empty($company_values)) {
    $company_values = [
        ['icon' => 'fa-heart', 'title' => 'Customer First', 'description' => 'Every decision we make puts our customers first. Your satisfaction is our priority.'],
        ['icon' => 'fa-shield-alt', 'title' => 'Trust & Integrity', 'description' => 'We believe in honest business practices and building long-term relationships based on trust.'],
        ['icon' => 'fa-star', 'title' => 'Quality Products', 'description' => 'We carefully select every product to ensure the highest quality standards.'],
        ['icon' => 'fa-truck', 'title' => 'Reliable Delivery', 'description' => 'Fast and secure delivery, because we know you can\'t wait to receive your order.'],
        ['icon' => 'fa-hand-holding-heart', 'title' => 'Community Focus', 'description' => 'We\'re proud to serve our local community and support Nigerian businesses.'],
        ['icon' => 'fa-leaf', 'title' => 'Sustainability', 'description' => 'Committed to eco-friendly practices and sustainable business operations.']
    ];
} else {
    $company_values = json_decode($company_values, true);
}

// Get team members (can be moved to database later)
$team_members = [
    [
        'name' => 'Ahmed Muhammed',
        'position' => 'Founder & CEO',
        'bio' => 'With over 10 years of experience in e-commerce, Ahmed founded Muhamuktar Global Venture with a vision to revolutionize online shopping in Nigeria.',
        'image' => 'team-1.jpg'
    ],
    [
        'name' => 'Fatima Ibrahim',
        'position' => 'Head of Operations',
        'bio' => 'Fatima ensures that every order is processed and delivered with care. Her attention to detail keeps our operations running smoothly.',
        'image' => 'team-2.jpg'
    ],
    [
        'name' => 'Oluwaseun Adeyemi',
        'position' => 'Customer Experience Manager',
        'bio' => 'Seun leads our customer support team, making sure every customer interaction is positive and helpful.',
        'image' => 'team-3.jpg'
    ],
    [
        'name' => 'Chioma Okonkwo',
        'position' => 'Product Quality Specialist',
        'bio' => 'Chioma carefully selects and tests every product to ensure they meet our high quality standards.',
        'image' => 'team-4.jpg'
    ]
];

// Get milestones
$milestones = [
    ['year' => '2020', 'event' => 'Company founded with a small team of 3'],
    ['year' => '2021', 'event' => 'Reached 10,000 happy customers'],
    ['year' => '2022', 'event' => 'Expanded product catalog to 5000+ items'],
    ['year' => '2023', 'event' => 'Opened new warehouse in Lagos'],
    ['year' => '2024', 'event' => 'Launched mobile app and reached 50,000 customers']
];
?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Our Story</h1>
                <p class="hero-subtitle">Building Nigeria's most trusted online marketplace since <?= $founded_year ?></p>
            </div>
        </div>
    </section>

    <!-- About Content -->
    <section class="about-section">
        <div class="container">
            <div class="about-grid">
                <div class="about-image">
                    <img src="<?= BASE_URL ?>assets/images/about-story.jpg" alt="Our Story">
                </div>
                <div class="about-text">
                    <h2 class="section-title">Who We Are</h2>
                    <?php if (!empty($about_content)): ?>
                        <?= nl2br(htmlspecialchars($about_content)) ?>
                    <?php else: ?>
                        <p>Muhamuktar Global Venture was born from a simple idea: to make quality products accessible to everyone in Nigeria. What started as a small online store has grown into a trusted marketplace serving thousands of customers across the nation.</p>
                        <p>We believe that online shopping should be convenient, secure, and enjoyable. That's why we carefully curate our product selection, partner with reliable suppliers, and invest in technology to make your shopping experience seamless.</p>
                        <p>Today, we're proud to offer thousands of products across multiple categories, from fashion and electronics to home and living essentials. But our mission remains the same: to provide exceptional value and service to every customer.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="mission-section">
        <div class="container">
            <div class="mission-grid">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h2>Our Mission</h2>
                    <p><?= htmlspecialchars($company_mission) ?></p>
                </div>
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h2>Our Vision</h2>
                    <p><?= htmlspecialchars($company_vision) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="values-section">
        <div class="container">
            <h2 class="section-title">Our Core Values</h2>
            <div class="values-grid">
                <?php foreach ($company_values as $value): ?>
                    <div class="value-card">
                        <i class="fas <?= $value['icon'] ?>"></i>
                        <h3><?= htmlspecialchars($value['title']) ?></h3>
                        <p><?= htmlspecialchars($value['description']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Milestones -->
    <section class="milestone-section">
        <div class="container">
            <h2 class="section-title">Our Journey</h2>
            <div class="milestone-timeline">
                <?php foreach ($milestones as $index => $milestone): ?>
                    <div class="milestone-item <?= $index % 2 == 0 ? 'left' : 'right' ?>">
                        <div class="milestone-year"><?= $milestone['year'] ?></div>
                        <div class="milestone-content">
                            <p><?= htmlspecialchars($milestone['event']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="container">
            <h2 class="section-title">Meet Our Team</h2>
            <div class="team-grid">
                <?php foreach ($team_members as $member): ?>
                    <div class="team-card">
                        <div class="team-image">
                            <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($member['image']) ?>" 
                                 alt="<?= htmlspecialchars($member['name']) ?>">
                        </div>
                        <div class="team-info">
                            <h3><?= htmlspecialchars($member['name']) ?></h3>
                            <p class="team-position"><?= htmlspecialchars($member['position']) ?></p>
                            <p class="team-bio"><?= htmlspecialchars($member['bio']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-number">50K+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-truck"></i>
                    <div class="stat-number">5K+</div>
                    <div class="stat-label">Orders Delivered</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <div class="stat-number">4.8</div>
                    <div class="stat-label">Customer Rating</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials-section">
        <div class="container">
            <h2 class="section-title">What Our Customers Say</h2>
            <div class="testimonials-slider">
                <div class="testimonial-card">
                    <i class="fas fa-quote-left"></i>
                    <p>"Amazing service! My order arrived earlier than expected and the quality was excellent. Will definitely shop again."</p>
                    <div class="testimonial-author">
                        <strong>Oluwaseun A.</strong>
                        <span>Lagos</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <i class="fas fa-quote-left"></i>
                    <p>"Great selection of products and very responsive customer service. They helped me find exactly what I was looking for."</p>
                    <div class="testimonial-author">
                        <strong>Chioma O.</strong>
                        <span>Abuja</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <i class="fas fa-quote-left"></i>
                    <p>"Best online shopping experience in Nigeria. The website is easy to use and delivery is always on time."</p>
                    <div class="testimonial-author">
                        <strong>Ahmed B.</strong>
                        <span>Kano</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-card">
                <h2>Ready to Start Shopping?</h2>
                <p>Join thousands of satisfied customers and experience the best in online shopping.</p>
                <div class="cta-buttons">
                    <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">Shop Now</a>
                    <a href="<?= BASE_URL ?>pages/contact.php" class="btn-secondary">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 6rem 0;
    text-align: center;
}

.hero-title {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: fadeInUp 1s ease;
}

.hero-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    animation: fadeInUp 1s ease 0.2s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* About Section */
.about-section {
    padding: 5rem 0;
    background: white;
}

.about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}

.about-image {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.about-image img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.3s;
}

.about-image:hover img {
    transform: scale(1.05);
}

.about-text {
    padding-right: 2rem;
}

.section-title {
    font-size: 2rem;
    margin-bottom: 1.5rem;
    color: var(--text);
    position: relative;
    padding-bottom: 0.5rem;
}

.section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: var(--primary);
}

.about-text p {
    color: var(--text-light);
    line-height: 1.8;
    margin-bottom: 1rem;
}

/* Mission Section */
.mission-section {
    padding: 5rem 0;
    background: var(--bg);
}

.mission-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 3rem;
}

.mission-card {
    background: white;
    padding: 3rem;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.mission-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.mission-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 2rem;
}

.mission-card h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.mission-card p {
    color: var(--text-light);
    line-height: 1.8;
}

/* Values Section */
.values-section {
    padding: 5rem 0;
    background: white;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.value-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: all 0.3s;
}

.value-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.value-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.value-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.value-card p {
    color: var(--text-light);
    line-height: 1.6;
}

/* Milestone Section */
.milestone-section {
    padding: 5rem 0;
    background: var(--bg);
}

.milestone-timeline {
    position: relative;
    max-width: 800px;
    margin: 3rem auto 0;
}

.milestone-timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 50%;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
    transform: translateX(-50%);
}

.milestone-item {
    position: relative;
    margin: 2rem 0;
    width: 50%;
}

.milestone-item.left {
    left: 0;
    padding-right: 3rem;
}

.milestone-item.right {
    left: 50%;
    padding-left: 3rem;
}

.milestone-year {
    position: absolute;
    top: 0;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
}

.milestone-item.left .milestone-year {
    right: -40px;
}

.milestone-item.right .milestone-year {
    left: -40px;
}

.milestone-content {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.milestone-content p {
    color: var(--text-light);
    line-height: 1.6;
    margin: 0;
}

/* Team Section */
.team-section {
    padding: 5rem 0;
    background: white;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.team-card {
    background: var(--bg);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.team-image {
    height: 250px;
    overflow: hidden;
}

.team-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.team-card:hover .team-image img {
    transform: scale(1.1);
}

.team-info {
    padding: 1.5rem;
}

.team-info h3 {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
    color: var(--text);
}

.team-position {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.team-bio {
    color: var(--text-light);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Stats Section */
.stats-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    text-align: center;
}

.stat-card i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
}

/* Testimonials Section */
.testimonials-section {
    padding: 5rem 0;
    background: var(--bg);
}

.testimonials-slider {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-top: 3rem;
}

.testimonial-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    position: relative;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.testimonial-card i {
    font-size: 2rem;
    color: var(--primary);
    opacity: 0.2;
    margin-bottom: 1rem;
}

.testimonial-card p {
    color: var(--text-light);
    line-height: 1.8;
    margin-bottom: 1.5rem;
    font-style: italic;
}

.testimonial-author {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}

.testimonial-author strong {
    display: block;
    color: var(--text);
    margin-bottom: 0.25rem;
}

.testimonial-author span {
    color: var(--text-light);
    font-size: 0.9rem;
}

/* CTA Section */
.cta-section {
    padding: 5rem 0;
    background: white;
}

.cta-card {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    padding: 4rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 30px;
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.3);
}

.cta-card h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.cta-card p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-primary {
    display: inline-block;
    padding: 1rem 2rem;
    background: white;
    color: var(--primary);
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.btn-secondary {
    display: inline-block;
    padding: 1rem 2rem;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 1200px) {
    .team-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .values-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .testimonials-slider {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .about-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .about-text {
        padding-right: 0;
    }
    
    .mission-grid {
        grid-template-columns: 1fr;
    }
    
    .milestone-timeline:before {
        left: 30px;
    }
    
    .milestone-item {
        width: 100%;
        left: 0 !important;
        padding-left: 80px !important;
        padding-right: 0 !important;
    }
    
    .milestone-item .milestone-year {
        left: 0 !important;
        right: auto !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .values-grid,
    .team-grid,
    .testimonials-slider {
        grid-template-columns: 1fr;
    }
    
    .cta-card {
        padding: 2rem;
    }
    
    .cta-card h2 {
        font-size: 2rem;
    }
    
    .cta-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .mission-card {
        padding: 2rem;
    }
}
</style>

<script>
// Add animation on scroll
window.addEventListener('scroll', function() {
    const elements = document.querySelectorAll('.value-card, .team-card, .milestone-item');
    
    elements.forEach(element => {
        const position = element.getBoundingClientRect();
        
        if (position.top < window.innerHeight - 100) {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }
    });
});

// Initialize elements with opacity 0
document.querySelectorAll('.value-card, .team-card, .milestone-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.5s ease';
});

// Trigger once on load
setTimeout(() => {
    window.dispatchEvent(new Event('scroll'));
}, 100);
</script>

<?php require_once '../includes/footer.php'; ?>