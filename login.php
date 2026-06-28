<?php
$currentPage = 'login';
$pageTitle = 'Login or Sign Up';
$pageDesc = 'Sign in to HealthDial to list your business, promote listings, claim a listing or raise a support request.';

require_once 'includes/db.php';
require_once 'includes/user_auth.php';
require_once 'includes/firebase_config.php';

$return = hd_safe_return_url($_GET['return'] ?? 'index.php');

// Already logged in? Go straight back.
if (hd_is_logged_in()) {
    header('Location: ' . $return);
    exit;
}

$fbConfig = hd_firebase_config();
$fbConfigured = hd_firebase_is_configured();

require_once 'includes/icons.php';
require_once 'includes/header.php';
?>

<section class="section" style="padding-top:140px; min-height:70vh;">
    <div class="container">
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="auth-head">
                    <img src="<?= $assetBase ?>/assets/images/logo.png" alt="HealthDial" class="auth-logo" />
                    <h1>Welcome to <span class="gradient-text">HealthDial</span></h1>
                    <p>Sign in to list your business, promote, claim a listing or get support.</p>
                </div>

                <?php if (!$fbConfigured): ?>
                <div class="auth-alert">
                    <i class="fas fa-triangle-exclamation"></i>
                    Login is not configured yet. Add your Firebase web config in
                    <code>includes/firebase_config.php</code> and enable Google + Phone sign-in.
                </div>
                <?php else: ?>

                <div id="authError" class="auth-alert" style="display:none;"></div>
                <div id="authNotice" class="auth-alert"
                    style="display:none; background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.35); color:#34d399;">
                </div>

                <!-- ===== Step: choose method ===== -->
                <div id="authMethods">
                    <button id="googleBtn" class="auth-btn auth-btn-google">
                        <svg width="18" height="18" viewBox="0 0 48 48">
                            <path fill="#EA4335"
                                d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                            <path fill="#4285F4"
                                d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                            <path fill="#FBBC05"
                                d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                            <path fill="#34A853"
                                d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                        </svg>
                        Continue with Google
                    </button>

                    <div class="auth-divider"><span>or use email</span></div>

                    <label class="auth-label" for="nameInput">Your name
                        <span style="font-weight:400;color:var(--text-muted,#64748b);">(for new accounts)</span></label>
                    <input type="text" id="nameInput" class="auth-input" placeholder="Your name" autocomplete="name"
                        style="margin-bottom:10px;" />
                    <label class="auth-label" for="emailInput">Email address</label>
                    <input type="email" id="emailInput" class="auth-input" placeholder="you@example.com"
                        autocomplete="email" />
                    <label class="auth-label" for="passwordInput" style="margin-top:10px;">Password</label>
                    <input type="password" id="passwordInput" class="auth-input" placeholder="At least 6 characters"
                        autocomplete="current-password" />
                    <div style="display:flex; gap:8px; margin-top:12px;">
                        <button id="emailSignInBtn" class="auth-btn auth-btn-primary" style="flex:1;">
                            <i class="fas fa-right-to-bracket"></i> Sign in
                        </button>
                        <button id="emailSignUpBtn" class="auth-btn auth-btn-google" style="flex:1;">
                            <i class="fas fa-user-plus"></i> Create account
                        </button>
                    </div>
                    <button id="forgotPwBtn" class="auth-btn auth-btn-ghost"
                        style="margin-top:6px; font-size:.82rem;">Forgot password?</button>
                </div>

                <div id="authLoading" class="auth-loading" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i> <span id="authLoadingText">Please wait…</span>
                </div>

                <p class="auth-terms">
                    By continuing you agree to our
                    <a href="<?= $assetBase ?>/terms-and-conditions.php">Terms</a> and
                    <a href="<?= $assetBase ?>/privacy-policy.php">Privacy Policy</a>.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
.auth-wrap {
    max-width: 440px;
    margin: 0 auto;
}

.auth-card {
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    border-radius: 20px;
    padding: 32px 28px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

[data-theme="light"] .auth-card {
    background: #fff;
}

.auth-head {
    text-align: center;
    margin-bottom: 22px;
}

.auth-logo {
    width: 52px;
    height: 52px;
    object-fit: contain;
    margin-bottom: 12px;
}

.auth-head h1 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 6px;
}

.auth-head p {
    font-size: .88rem;
    color: var(--text-secondary, #94a3b8);
    line-height: 1.5;
}

.auth-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 13px 18px;
    border-radius: 12px;
    font-weight: 700;
    font-size: .95rem;
    cursor: pointer;
    border: none;
    font-family: inherit;
    transition: transform .15s, box-shadow .15s, background .15s;
}

.auth-btn:active {
    transform: scale(.99);
}

.auth-btn-google {
    background: #fff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
}

.auth-btn-google:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
}

.auth-btn-primary {
    background: linear-gradient(135deg, #2563eb, #10b981);
    color: #fff;
    box-shadow: 0 6px 20px rgba(37, 99, 235, .35);
}

.auth-btn-primary:hover {
    transform: translateY(-1px);
}

.auth-btn-ghost {
    background: transparent;
    color: var(--text-muted, #94a3b8);
}

.auth-divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 18px 0;
    color: var(--text-muted, #64748b);
    font-size: .8rem;
}

.auth-divider::before,
.auth-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--glass-border, rgba(255, 255, 255, .12));
}

.auth-divider span {
    padding: 0 12px;
}

.auth-label {
    display: block;
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-secondary, #94a3b8);
    margin-bottom: 6px;
}

.auth-phone-row {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.auth-cc {
    display: flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 12px;
    font-weight: 700;
    background: var(--glass-border, rgba(255, 255, 255, .06));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, .12));
}

.auth-input {
    width: 100%;
    padding: 13px 14px;
    border-radius: 12px;
    font-size: .95rem;
    font-family: inherit;
    background: rgba(255, 255, 255, .04);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, .14));
    color: var(--text, #f1f5f9);
    outline: none;
}

[data-theme="light"] .auth-input {
    background: #f8fafc;
    color: #0f172a;
}

.auth-input:focus {
    border-color: #2563eb;
}

.auth-otp-input {
    text-align: center;
    letter-spacing: .5em;
    font-size: 1.3rem;
    font-weight: 700;
}

.auth-otp-hint {
    font-size: .85rem;
    color: var(--text-secondary, #94a3b8);
    text-align: center;
    margin-bottom: 12px;
}

.auth-alert {
    background: rgba(239, 68, 68, .1);
    border: 1px solid rgba(239, 68, 68, .3);
    color: #fca5a5;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: .84rem;
    margin-bottom: 16px;
    line-height: 1.5;
}

.auth-alert code {
    background: rgba(0, 0, 0, .25);
    padding: 1px 6px;
    border-radius: 5px;
}

.auth-loading {
    text-align: center;
    color: var(--text-secondary, #94a3b8);
    font-size: .9rem;
    margin-top: 16px;
}

.auth-terms {
    text-align: center;
    font-size: .75rem;
    color: var(--text-muted, #64748b);
    margin-top: 18px;
    line-height: 1.5;
}

.auth-terms a {
    color: var(--blue, #60a5fa);
}
</style>

<?php if ($fbConfigured): ?>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js"></script>
<script>
(function() {
    var firebaseConfig = <?= json_encode([
                'apiKey' => $fbConfig['apiKey'],
                'authDomain' => $fbConfig['authDomain'],
                'projectId' => $fbConfig['projectId'],
                'storageBucket' => $fbConfig['storageBucket'],
                'messagingSenderId' => $fbConfig['messagingSenderId'],
                'appId' => $fbConfig['appId'],
            ], JSON_UNESCAPED_SLASHES) ?>;
    var RETURN_URL = <?= json_encode($return) ?>;

    firebase.initializeApp(firebaseConfig);
    var auth = firebase.auth();

    var $ = function(id) {
        return document.getElementById(id);
    };

    function showError(msg) {
        var el = $('authError');
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearError() {
        $('authError').style.display = 'none';
        var n = $('authNotice');
        if (n) n.style.display = 'none';
    }

    function showSuccess(msg) {
        var el = $('authNotice');
        el.textContent = msg;
        el.style.display = 'block';
        $('authError').style.display = 'none';
    }

    function setLoading(on, text) {
        $('authLoading').style.display = on ? 'block' : 'none';
        if (text) $('authLoadingText').textContent = text;
    }

    // POST the verified token(s) to the server bridge → it sets the session.
    function sendToBridge(payload) {
        setLoading(true, 'Signing you in…');
        payload.return = RETURN_URL;
        return fetch('auth-bridge.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function(r) {
            return r.json();
        }).then(function(res) {
            if (res.success && res.redirect) {
                window.location.href = res.redirect;
                return;
            }
            setLoading(false);
            showError(res.message || 'Sign-in failed. Please try again.');
        }).catch(function() {
            setLoading(false);
            showError('Network error. Please try again.');
        });
    }

    // ---- Google ----
    $('googleBtn').addEventListener('click', function() {
        clearError();
        var provider = new firebase.auth.GoogleAuthProvider();
        setLoading(true, 'Opening Google…');
        auth.signInWithPopup(provider).then(function(result) {
            return result.user.getIdToken();
        }).then(function(idToken) {
            return sendToBridge({
                id_token: idToken
            });
        }).catch(function(err) {
            setLoading(false);
            showError(humanError(err));
        });
    });

    // ---- Email / Password ----
    $('emailSignInBtn').addEventListener('click', function () {
        clearError();
        var email = ($('emailInput').value || '').trim();
        var pw = $('passwordInput').value || '';
        if (!email || pw.length < 6) {
            showError('Enter your email and password (at least 6 characters).');
            return;
        }
        setLoading(true, 'Signing in…');
        auth.signInWithEmailAndPassword(email, pw).then(function (cred) {
            if (!cred.user.emailVerified) {
                // Block unverified accounts; resend the link and sign back out.
                return cred.user.sendEmailVerification().catch(function () {}).then(function () {
                    return auth.signOut();
                }).then(function () {
                    setLoading(false);
                    showError('Please verify your email first. We just re-sent a verification link to ' + email + '.');
                    throw 'unverified';
                });
            }
            return cred.user.getIdToken().then(function (idToken) {
                return sendToBridge({ id_token: idToken });
            });
        }).catch(function (err) {
            if (err === 'unverified') return;
            setLoading(false);
            showError(humanError(err));
        });
    });

    $('emailSignUpBtn').addEventListener('click', function () {
        clearError();
        var email = ($('emailInput').value || '').trim();
        var pw = $('passwordInput').value || '';
        var name = ($('nameInput').value || '').trim();
        if (!email || pw.length < 6) {
            showError('Enter an email and a password of at least 6 characters.');
            return;
        }
        setLoading(true, 'Creating account…');
        auth.createUserWithEmailAndPassword(email, pw).then(function (cred) {
            var p = name ? cred.user.updateProfile({ displayName: name }) : Promise.resolve();
            return p.then(function () {
                return cred.user.sendEmailVerification();
            }).then(function () {
                return auth.signOut(); // don't log in until verified
            });
        }).then(function () {
            setLoading(false);
            showSuccess('Account created! We sent a verification link to ' + email + '. Open it, then come back and Sign in.');
        }).catch(function (err) {
            setLoading(false);
            showError(humanError(err));
        });
    });

    $('forgotPwBtn').addEventListener('click', function () {
        clearError();
        var email = ($('emailInput').value || '').trim();
        if (!email) {
            showError('Enter your email address above first.');
            return;
        }
        auth.sendPasswordResetEmail(email).then(function () {
            showSuccess('Password reset link sent to ' + email + '.');
        }).catch(function (err) {
            showError(humanError(err));
        });
    });

    function humanError(err) {
        var code = err && err.code ? err.code : '';
        if (code === 'auth/popup-closed-by-user') return 'Sign-in cancelled.';
        if (code === 'auth/email-already-in-use') return 'This email already has an account. Please Sign in instead.';
        if (code === 'auth/invalid-email') return 'Please enter a valid email address.';
        if (code === 'auth/weak-password') return 'Password should be at least 6 characters.';
        if (code === 'auth/wrong-password' || code === 'auth/invalid-credential') return 'Incorrect email or password.';
        if (code === 'auth/user-not-found') return 'No account found with that email. Use "Create account".';
        if (code === 'auth/user-disabled') return 'This account has been disabled.';
        if (code === 'auth/invalid-verification-code') return 'Incorrect OTP. Please try again.';
        if (code === 'auth/too-many-requests') return 'Too many attempts. Please try again later.';
        if (code === 'auth/invalid-phone-number') return 'Invalid phone number.';
        if (code === 'auth/invalid-app-credential' || code === 'auth/captcha-check-failed')
            return 'reCAPTCHA check failed. Please solve the reCAPTCHA and try again (on localhost use a Firebase test phone number).';
        if (code === 'auth/unauthorized-domain')
            return 'This domain is not authorized in Firebase. Add it under Authentication → Settings → Authorized domains.';
        return (err && err.message) ? err.message : 'Something went wrong. Please try again.';
    }
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>