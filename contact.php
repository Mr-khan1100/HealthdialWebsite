<?php
$currentPage = 'contact';
$pageTitle = 'Contact Us | Book Diagnostic Tests Online | Pharmacies Across India | Health Dial';
$pageDesc = 'Healthdial is One Stop Source for finding , Doctors, Pharmacies, medical labs, Hospitals and clinics, all at your fingertips. Download Health dial app for registration.';
require_once 'includes/icons.php';
require_once 'includes/header.php';
?>

<section class="section" style="padding-top: 140px;">
    <div class="container">
        <div class="section-header">
            <span class="section-label">
                <?= icon('mail') ?> Contact
            </span>
            <h2 class="section-title">Get In <span class="gradient-text">Touch</span></h2>
            <p class="section-subtitle">We'd love to hear from you. Whether you have a question, feedback, or want to
                list your business.</p>
        </div>

        <div class="contact-grid">
            <div class="contact-form">
                <h3 style="font-size: var(--fs-xl); font-weight: 700; margin-bottom: 24px;">Send us a message</h3>
                <form action="#" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="name">
                            <?= icon('user') ?> Full Name
                        </label>
                        <input type="text" class="form-input" id="name" name="name" placeholder="Your full name"
                            required />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">
                            <?= icon('mail') ?> Email Address
                        </label>
                        <input type="email" class="form-input" id="email" name="email" placeholder="your@email.com"
                            required />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">
                            <?= icon('phone') ?> Phone Number
                        </label>
                        <input type="tel" class="form-input" id="phone" name="phone" placeholder="+91 XXXXX XXXXX" />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="subject">
                            <?= icon('news') ?> Subject
                        </label>
                        <input type="text" class="form-input" id="subject" name="subject" placeholder="How can we help?"
                            required />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="message">
                            <?= icon('mail') ?> Message
                        </label>
                        <textarea class="form-textarea" id="message" name="message"
                            placeholder="Write your message here..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <?= icon('arrowRight') ?> Send Message
                    </button>
                </form>
            </div>

            <div>
                <div class="card reveal delay-1" style="margin-bottom: 24px;">
                    <div class="card-icon blue">
                        <?= icon('mail') ?>
                    </div>
                    <h3 class="card-title">Email Us</h3>
                    <p class="card-text">For general inquiries and support:</p>
                    <a href="mailto:healthdialofficial@gmail.com"
                        style="color: var(--blue); font-weight: 600; font-size: var(--fs-sm);">healthdialofficial@gmail.com</a>
                </div>

                <div class="card reveal delay-2" style="margin-bottom: 24px;">
                    <div class="card-icon green">
                        <?= icon('phone') ?>
                    </div>
                    <h3 class="card-title">Call Us</h3>
                    <p class="card-text">Available Monday to Saturday, 10 AM - 6 PM:</p>
                    <a href="tel:+919911660669"
                        style="color: var(--green); font-weight: 600; font-size: var(--fs-sm);">+91 9911660669</a>
                </div>

                <div class="card reveal delay-3" style="margin-bottom: 24px;">
                    <div class="card-icon blue">
                        <?= icon('hospital') ?>
                    </div>
                    <h3 class="card-title">List Your Business</h3>
                    <p class="card-text">Are you a hospital, clinic, or medical provider? List your business on
                        HealthDial for free.</p>
                    <a href="#" class="btn btn-secondary" style="margin-top: 12px; font-size: var(--fs-sm);">
                        <?= icon('arrowRight') ?> Get Started
                    </a>
                </div>

                <div class="card reveal delay-4">
                    <div class="card-icon green">
                        <?= icon('location') ?>
                    </div>
                    <h3 class="card-title">Our Office</h3>
                    <p class="card-text">HealthDial Pvt. Ltd.<br />India</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>