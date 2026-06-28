<?php
$currentPage = 'contact';
$pageTitle = 'Contact Us | Book Diagnostic Tests Online | Pharmacies Across India | Health Dial';
$pageDesc = 'Healthdial is One Stop Source for finding , Doctors, Pharmacies, medical labs, Hospitals and clinics, all at your fingertips. Download Health dial app for registration.';
require_once 'includes/db.php';
require_once 'includes/user_auth.php';
// Support contact must come from a logged-in, phone-verified user (2-step).
$hdUser = hd_require_phone_verified();

/**
 * Ensures support_tickets has guest_* columns so website visitors (who have no
 * user account) can raise tickets. Idempotent / self-healing.
 */
function hd_ensure_support_guest_columns($conn)
{
    $existing = [];
    $res = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets'"
    );
    if ($res) {
        while ($row = $res->fetch_assoc())
            $existing[] = $row['COLUMN_NAME'];
    }
    $add = [];
    if (!in_array('guest_name', $existing))  $add[] = "ADD COLUMN guest_name VARCHAR(150) NULL";
    if (!in_array('guest_email', $existing)) $add[] = "ADD COLUMN guest_email VARCHAR(190) NULL";
    if (!in_array('guest_phone', $existing)) $add[] = "ADD COLUMN guest_phone VARCHAR(30) NULL";
    if (!in_array('source', $existing))      $add[] = "ADD COLUMN source VARCHAR(30) NULL";
    if ($add) {
        @$conn->query("ALTER TABLE support_tickets " . implode(', ', $add));
    }
}

// ── Handle contact form submission → create a support ticket ──
$contactErrors = [];
$old = [
    'name'    => $hdUser['name'] ?? '',
    'email'   => $hdUser['email'] ?? '',
    'phone'   => $hdUser['mobile'] ?? '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Honeypot: real users never fill the hidden "website" field
    if (!empty($_POST['website'])) {
        header('Location: contact.php?sent=1');
        exit;
    }

    foreach ($old as $k => $_unused) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if ($old['name'] === '')
        $contactErrors['name'] = 'Please enter your name.';
    if ($old['email'] === '')
        $contactErrors['email'] = 'Please enter your email.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $contactErrors['email'] = 'Please enter a valid email address.';
    if ($old['subject'] === '')
        $contactErrors['subject'] = 'Please enter a subject.';
    if ($old['message'] === '')
        $contactErrors['message'] = 'Please enter a message.';

    if (!$contactErrors) {
        $conn = getDbConnection();
        if (!$conn) {
            $contactErrors['general'] = 'Service temporarily unavailable. Please try again in a moment.';
        } else {
            hd_ensure_support_guest_columns($conn);
            $conn->begin_transaction();
            try {
                $uid = (int) $hdUser['id'];
                $ins = $conn->prepare(
                    "INSERT INTO support_tickets (user_id, subject, status, guest_name, guest_email, guest_phone, source)
                     VALUES (?, ?, 'open', ?, ?, ?, 'contact_form')"
                );
                $ins->bind_param('issss', $uid, $old['subject'], $old['name'], $old['email'], $old['phone']);
                $ins->execute();
                $ticketId = $conn->insert_id;
                $ins->close();

                $msg = $conn->prepare(
                    "INSERT INTO ticket_messages (ticket_id, sender_type, message) VALUES (?, 'user', ?)"
                );
                $msg->bind_param('is', $ticketId, $old['message']);
                $msg->execute();
                $msg->close();

                $conn->commit();
                header('Location: contact.php?sent=1');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $contactErrors['general'] = 'Something went wrong while sending your message. Please try again.';
            }
        }
    }
}

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
                <?php if (isset($_GET['sent'])): ?>
                    <div class="card" style="text-align:center; padding:44px 28px;">
                        <div class="card-icon green" style="margin:0 auto 18px;">
                            <?= icon('check') ?>
                        </div>
                        <h3 class="card-title" style="font-size: var(--fs-xl);">Message Sent!</h3>
                        <p class="card-text">Thanks for reaching out. Our support team has received your message and
                            will get back to you shortly.</p>
                        <a href="contact.php" class="btn btn-secondary" style="margin-top: 18px;">
                            <?= icon('arrowRight') ?> Send Another Message
                        </a>
                    </div>
                <?php else: ?>
                    <h3 style="font-size: var(--fs-xl); font-weight: 700; margin-bottom: 24px;">Send us a message</h3>
                    <?php if (!empty($contactErrors['general'])): ?>
                        <div class="card"
                            style="border:1px solid #ef4444; background:rgba(239,68,68,0.08); padding:14px 16px; margin-bottom:20px; color:#ef4444; font-size:var(--fs-sm);">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($contactErrors['general']) ?>
                        </div>
                    <?php endif; ?>
                    <form action="contact.php" method="POST">
                        <input type="hidden" name="contact_submit" value="1" />
                        <!-- Honeypot: hidden from real users, traps bots -->
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
                            style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;" />
                        <div class="form-group">
                            <label class="form-label" for="name">
                                <?= icon('user') ?> Full Name
                            </label>
                            <input type="text" class="form-input" id="name" name="name" placeholder="Your full name"
                                value="<?= htmlspecialchars($old['name']) ?>" required />
                            <?php if (!empty($contactErrors['name'])): ?>
                                <span style="color:#ef4444; font-size:var(--fs-xs);"><?= htmlspecialchars($contactErrors['name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">
                                <?= icon('mail') ?> Email Address
                            </label>
                            <input type="email" class="form-input" id="email" name="email" placeholder="your@email.com"
                                value="<?= htmlspecialchars($old['email']) ?>" required />
                            <?php if (!empty($contactErrors['email'])): ?>
                                <span style="color:#ef4444; font-size:var(--fs-xs);"><?= htmlspecialchars($contactErrors['email']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">
                                <?= icon('phone') ?> Phone Number
                            </label>
                            <input type="tel" class="form-input" id="phone" name="phone" placeholder="+91 XXXXX XXXXX"
                                value="<?= htmlspecialchars($old['phone']) ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subject">
                                <?= icon('news') ?> Subject
                            </label>
                            <input type="text" class="form-input" id="subject" name="subject"
                                placeholder="How can we help?" value="<?= htmlspecialchars($old['subject']) ?>" required />
                            <?php if (!empty($contactErrors['subject'])): ?>
                                <span style="color:#ef4444; font-size:var(--fs-xs);"><?= htmlspecialchars($contactErrors['subject']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="message">
                                <?= icon('mail') ?> Message
                            </label>
                            <textarea class="form-textarea" id="message" name="message"
                                placeholder="Write your message here..." required><?= htmlspecialchars($old['message']) ?></textarea>
                            <?php if (!empty($contactErrors['message'])): ?>
                                <span style="color:#ef4444; font-size:var(--fs-xs);"><?= htmlspecialchars($contactErrors['message']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                            <?= icon('arrowRight') ?> Send Message
                        </button>
                    </form>
                <?php endif; ?>
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